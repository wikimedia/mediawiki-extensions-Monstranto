<?php

namespace MediaWiki\Extension\Monstranto;

use FormatJson;
use Html;
use MediaWiki\MediaWikiServices;
use RequestContext;
use Scribunto_LuaError;
use Scribunto_LuaLibraryBase;

class Lua extends Scribunto_LuaLibraryBase {

	/**
	 * @inheritDoc
	 */
	public function register() {
		$callbacks = [
			'addIllustration' => [ $this, 'addIllustration' ]
		];
		return $this->getEngine()->registerInterface(
			__DIR__ . '/../lua/mw.ext.monstranto.lua',
			$callbacks,
			[] /* arguments to setup function */
		);
	}

	/**
	 * Insert a strip marker representing the illustration
	 *
	 * @param array $args 1 arg that is table containing named arguments
	 *    svgText - String text of svg file
	 *    svgFile - String "File:SomeFile.svg"
	 *    border - boolean
	 *    activation - One of 'none', 'button' or 'click'
	 *    activationCallback - array [ moduleName, function ] client side callback.
	 *    callbackParameter - array
	 * @return array
	 */
	public function addIllustration( $args ) {
		$this->checkType( 'addIllustration', 1, $args, 'table' );

		$svgText = $this->getValue( $args, 'svgText', 'string' );
		$svgFile = $this->getValue( $args, 'svgFile', 'string' );
		$width = $this->getValue( $args, 'width', 'number', -1 );
		$height = $this->getValue( $args, 'height', 'number', -1 );
		$style = $this->getValue( $args, 'style', 'string' );
		// Todo, verify its a table containing only primitive types?
		// maybe also verify none of the keys are named __proto__ as a paranoid measure.
		$callbackParameter = $args['callbackParameter'] ?? null;

		if ( ( $svgText === null && $svgFile === null )
			|| ( $svgText !== null && $svgFile !== null )
		) {
			throw new Scribunto_LuaError( "addIllustration(): exactly one of svgText or svgFile must be specified" );
		}
		// $border
		// It would be cool if we could pass a real function here.
		$activationCallback = $this->getValue( $args, 'activationCallback', 'table' );
		if ( is_array( $activationCallback )
			&& ( !is_string( $activationCallback[1] ) || !is_string( $activationCallback[2] ) )
		) {
			throw new Scribunto_LuaError( "addIllustration() given invalid activationCallback callback" );
		}
		$activation = $this->getValue( $args, 'activation', 'string', $activationCallback ? 'button' : 'none' );
		if ( !in_array( $activation, [ 'none', 'button', 'click' ] ) ) {
			throw new Scribunto_LuaError( "addIllustration() given invalid activation argument" );
		}

		if ( $svgText !== null ) {
			$SVGInfo = MediaWikiServices::getInstance()->getService( SVGInfo::SERVICE_NAME );

			$info = $SVGInfo->getSvgInfo( $svgText );

			if ( $info['securityIssue'] !== false ) {
				// This is more for the better UI than anything else.
				// I have more faith in the client side DOMPurify than this
				// server side check.
				// TODO: We could also include the message explaining what is wrong.
				throw new Scribunto_LuaError( "SVG is not allowed to have scripts in it" );
			}

			$aspect = $info['width'] / $info['height'];

			if ( $width === -1 && $height === -1 ) {
				$width = $info['width'];
				$height = $info['height'];
			} elseif ( $width === -1 && $height !== -1 ) {
				$width = $aspect * $height;
			} elseif ( $width !== -1 && $height === -1 ) {
				$height = $aspect / $height;
			}
		}
		return [ $this->doAddIllustration(
			[
				'svgText' => $svgText,
				'svgFile' => $svgFile,
				'activation' => $activation,
				'activationCallback' => array_values( $activationCallback ),
				'width' => (int)$width,
				'height' => (int)$height,
				'style' => $style,
				'callbackParameter' => $callbackParameter
			]
		) ];
	}

	/**
	 * Get a unique id number
	 *
	 * @return string
	 */
	private function getId() {
		$curId = (int)$this->getParser()->getOutput()->getExtensionData( 'monstranto-id' ) + 1;
		$this->getParser()->getOutput()->setExtensionData( 'monstranto-id', $curId );
		return $curId . '-' . mt_rand();
	}

	/**
	 * Do real work of addIllustration
	 *
	 * @param array $args Arguments, already verified
	 * @return string Strip item for lua
	 */
	private function doAddIllustration( array $args ) {
		$parser = $this->getParser();
		$parser->getOutput()->addModuleStyles( [ 'ext.monstranto.styles' ] );
		$parser->getOutput()->addModules( [ 'ext.monstranto.init' ] );
		$csp = Api\Bootstrap::getCSP( RequestContext::getMain()->getConfig() );
		$jsonData = FormatJson::encode( $args, false, FormatJson::ALL_OK );
		$id = $this->getId();
		return $parser->insertStripItem(
			Html::element(
				'iframe',
				[
					'id' => 'mw-monstranto-frame-' . $id,
					'class' => 'mw-monstranto',
					'src' => $this->getBootstrap() . '#monstranto-id=' . $id ,
					'sandbox' => 'allow-scripts',
					// We also put CSP on api response.
					// attribute is not supported on all browsers.
					'csp' => $csp,
					'data-mw-monstranto' => $jsonData,
					'width' => $args['width'],
					'height' => $args['height'],
					'style' => $args['style'],
				]
			)
		);
	}

	/**
	 * Get api bootstrap url
	 *
	 * @return string
	 */
	private function getBootstrap(): string {
		return wfExpandUrl(
			wfAppendQuery(
				wfScript( 'api' ),
				[ 'action' => 'monstrantobootstrap' ]
			),
			PROTO_CANONICAL
		);
	}

	/**
	 * Get a value, checking its type, with a fallback if unspecified
	 *
	 * @param array $args Associative array
	 * @param string $name Name of argument
	 * @param string $expectedType
	 * @param mixed $default
	 * @return mixed
	 */
	public function getValue( $args, $name, $expectedType, $default = null ) {
		if ( !isset( $args[$name] ) ) {
			return $default;
		}

		$type = $this->getLuaType( $args[$name] );
		if ( $type !== $expectedType ) {
			throw new Scribunto_LuaError(
				"bad argument $name to addIllustration ($expectedType expected, got $type)"
			);
		}
		return $args[$name];
	}
}
