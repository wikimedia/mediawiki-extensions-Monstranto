<?php
namespace MediaWiki\Extension\Monstranto\Api;

use ApiBase;
use ApiFormatRaw;
use ApiMain;
use ApiResult;
use Config;
use Exception;
use MediaWiki\MainConfigNames;

class Bootstrap extends ApiBase {

	public function __construct(
		ApiMain $main,
		string $action
	) {
		parent::__construct( $main, $action );
	}

	public function isInternal() {
		return true;
	}

	public function getCustomPrinter() {
		$printer = new ApiFormatRaw( $this->getMain(), null );
		$printer->setFailWithHTTPError( true );
		return $printer;
	}

	public function execute() {
		$result = $this->getResult();
		$params = $this->extractRequestParams();

		$this->getMain()->setCacheMode( 'public' );
		$this->getMain()->setCacheMaxAge( 3600 ); // FIXME maybe longer?
		$result->addValue( null, 'text', $this->getHTML(), ApiResult::NO_SIZE_CHECK );
		$result->addValue( null, 'mime', 'text/html', ApiResult::NO_SIZE_CHECK );
		$result->addValue( null, 'filename', 'monstranto-bootstrap.htm', ApiResult::NO_SIZE_CHECK );
		// Override core. Note, CSP also overrides X-Frame-Options so this is a bit moot.
		$this->getMain()->getRequest()->response()->header(
			'Content-Security-Policy:' . self::getCSP( $this->getConfig() )
			. ' ; sandbox allow-scripts'
		);
	}

	public static function getCSP( Config $config ) {
		// Static so we can use this elsewhere.
		// FIXME broken if using protocol relative.
		$assetPath = wfExpandUrl(
			$config->get( MainConfigNames::ExtensionAssetsPath ) . '/Monstranto/resources/iframe/',
			PROTO_CANONICAL
		);
		if ( strpos( $assetPath, ',' ) !== false || strpos( $assetPath, ';' ) !== false ) {
			throw new Exception( "invalid csp" );
		}
		// FIXME, should we allow loading images? other media? sounds?
		return "frame-ancestors 'self'; connect-src data:; script-src 'unsafe-eval' $assetPath";
	}

	/**
	 * Get the html of response
	 *
	 * @return string HTML
	 */
	public function getHTML() {
		$config = $this->getConfig();
		$html = file_get_contents( __DIR__ . '/../../resources/iframe/monstranto-bootstrap.htm' );

		if ( $html === false ) {
			throw new Exception( "Can't read monstranto iframe" );
		}

		// Not clear if this is the best approach. Could use a mustache template.
		// Should we be using RL?

		// FIXME This doesn't work if $wgServer is protocol relative!
		$origin = htmlspecialchars( $config->get( MainConfigNames::CanonicalServer ) );
		// Current CSP only works with canonical url.
		$basePath = htmlspecialchars(
			wfExpandUrl(
				$config->get( MainConfigNames::ExtensionAssetsPath ) .
					'/Monstranto/resources/iframe',
				PROTO_CANONICAL
			)
		);
		$html = str_replace(
			[ '$$ORIGIN$$', '$$BASEPATH$$' ],
			[ $origin, $basePath ],
			$html
		);
		return $html;
	}
}
