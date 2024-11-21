<?php

namespace MediaWiki\Extension\Monstranto;

class Hooks implements
	\MediaWiki\Extension\Scribunto\Hooks\ScribuntoExternalLibrariesHook
{

	/**
	 * Hook to load lua library
	 *
	 * @param string $engine
	 * @param array &$extraLibraries
	 */
	public function onScribuntoExternalLibraries( $engine, &$extraLibraries ) {
		$extraLibraries['mw.ext.monstranto'] = Lua::class;
	}
}
