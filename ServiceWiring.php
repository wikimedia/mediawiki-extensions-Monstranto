<?php

use MediaWiki\Extension\Monstranto\SVGInfo;
use MediaWiki\MediaWikiServices;

return [
	SVGInfo::SERVICE_NAME => static function ( MediaWikiServices $services ): SVGInfo {
		return new SVGInfo( $services->getMainConfig(), $services->getTempFSFileFactory() );
	}
];
