<?php

namespace MediaWiki\Extension\Monstranto;

use BadMethodCallException;
use Config;
use Exception;
use MediaWiki\FileBackend\FSFile\TempFSFileFactory;
use RuntimeException;
use SVGReader;
use UploadBase;

// This is a bit ugly

class SVGInfo extends UploadBase {

	public const SERVICE_NAME = "MonstrantoSVGInfo";
	/** @var Config */
	private $config;
	/** @var TempFSFileFactory */
	private $tmpFactory;

	/**
	 * @param \WebRequest &$request
	 * @return never
	 */
	public function initializeFromRequest( &$request ) {
		throw new BadMethodCallException( "unimplemented" );
	}

	/**
	 * @param Config $config
	 * @param TempFSFileFactory $tmpFactory
	 */
	public function __construct( Config $config, TempFSFileFactory $tmpFactory ) {
		$this->config = $config;
		$this->tmpFactory = $tmpFactory;
	}

	/**
	 * @param string $svg SVG to check
	 * @return array
	 */
	public function getSVGInfo( $svg ) {
		// Unfortunately all MW code seems to want files not strings.
		$file = $this->tmpFactory->newTempFSFile( 'monstranto_', 'svg' );
		if ( !$file ) {
			throw new RuntimeException( "Cannot create temp file" );
		}
		file_put_contents( $file->getPath(), $svg );

		$scriptCheck = $this->detectScriptInSvg( $file->getPath(), false );
		try {
			$svgReader = new SVGReader( $file->getPath() );
			return $svgReader->getMetadata() + [ 'securityIssue' => $scriptCheck ];
		} catch ( Exception $e ) {
			// not sure if we should do something with this? throw an error?
			// Primary cause seems to be an <svg> tag without a namespace decleartion.
			return [
				'securityIssue' => $scriptCheck,
				'width' => 512, // SVGReader::DEFAULT_WIDTH
				'height' => 512, // SVGReader::DEFAULT_HEIGHT
			];
		}
	}
}
