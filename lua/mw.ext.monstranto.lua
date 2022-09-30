local monstranto = {}
local php

function monstranto.setupInterface( options )
	-- Remove setup function
	monstranto.setupInterface = nil

	-- Copy the PHP callbacks to a local variable, and remove the global
	php = mw_interface
	mw_interface = nil

	-- Do any other setup here

	-- Install into the mw global
	mw = mw or {}
	mw.ext = mw.ext or {}
	mw.ext.monstranto = monstranto

	-- Indicate that we're loaded
	package.loaded['mw.ext.monstranto'] = monstranto
end

function monstranto.addIllustration( args )
	return php.addIllustration( args )
end

return monstranto

