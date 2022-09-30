<?php

namespace MediaWiki\Extension\Monstranto;

class Hooks {

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
