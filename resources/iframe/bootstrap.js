( function () {

const expectedOrigin = document.querySelector( 'meta[name=expected-origin]' ).content;
var svgLoaded = false;
// The idea behind using a hash instead of query param, is we could have better varnish cache hit ratio.
const id = location.hash.match( /^#monstranto-id=[a-zA-Z0-9_-]+$/ ) ?
	location.hash.replace( /^#monstranto-id=/, '' ) :
	false;
var intervalId = false;
// states go: uninitialized, waiting for svg, unactivated, waiting for lua, activated
var state = "uninitialized";

var doError = function( error ) {
	console.log( "[Monstranto Error] " + error );
	var strong = document.createElement( 'strong' );
	strong.className = 'error';
	strong.appendChild( document.createTextNode( error ) );
	document.body.replaceChildren( strong );
	document.body.className = 'errored';
	state = "error";
}

window.addEventListener( 'message', function ( event ) {
	if ( event.origin !== expectedOrigin || event.data.monstranto !== 'client' ) {
		console.log( "Discarding message with wrong origin or type" );
		return;
	}

	switch ( event.data.type ) {
	case 'setSVG':
		if (state != "waiting for svg") {
			console.log( "SVG already loaded, cannot reload" );
			return;
		}
		if ( intervalId !== false ) {
			clearInterval( intervalId );
		}
		state = "unactivated";
		if ( event.data.svg === undefined ) {
			doError( "no svg data" );
			return;
		}
		// Should we try and strip links? Currently the iframe
		// sandbox does not let them be clicked. Or should we allow them?
		// Should we allow HTML elements, or be strict with svg.
		safeSVG = DOMPurify.sanitize( event.data.svg, {
			USE_PROFILES: { svg: true, svgFilters: true },
			NAMESPACE: 'http://www.w3.org/2000/svg'
		} );
		var activation = '';
		if ( event.data.activation === 'click' ) {
			activation = '<div id="mw-activation-overlay"></div>';
		}
		if ( event.data.activation === 'button' ) {
			activation = '<div id="mw-activation-overlay"><div id="mw-activation-button">⏵</div></div>';
		}

		// We are using sandboxing on this iframe.
		document.body.innerHTML = activation + event.data.svg;

		if ( activation ) {
			overlay = document.getElementById( 'mw-activation-overlay' );
			overlay.addEventListener( 'click', function () {
				state = "waiting for lua";
				document.body.className = 'pending';
				overlay.parentElement.removeChild(overlay);
				msg = {
					monstranto: 'server',
					type: 'activate',
					id: id
				}
				window.parent.postMessage( msg, expectedOrigin );
			} );
		}
		break;
	case 'activate-nocallback':
		// Mostly a no-op
		if ( state === 'waiting for lua' ) {
			state = 'activated';
			document.body.className = '';
			console.log( "Activating, but nocallback" );
		} else {
			console.log( "ignoring activate-nocallback due to wrong state" );
		}
		break;
	case 'activate':
		if ( state === 'waiting for lua' ) {
			state = 'activated';
			document.body.className = '';
			console.log( "activating" );
			console.log( event.data.moduleText );
			console.log( event.data.callback );
			console.log( event.data.callbackParameter );
			startLua( event.data.moduleText, '@' + event.data.moduleName, event.data.callback, event.data.callbackParameter );
		} else {
			console.log( "ignoring activate due to wrong state" );
		}
		break;
	case 'error':
		state = 'error';
		doError( event.data.msg );
		break;
	default:
		console.log( "Got unrecogonized message of type " + event.data.type );
	}

} ); 

if (id !== false && window.parent !== window ) {
	var askForSVG = function () {
		if ( state !== 'waiting for svg' ) {
			return;
		}
		data = {
			monstranto: 'server',
			type: 'getSVG',
			id: id
		}
		window.parent.postMessage( data, expectedOrigin );
	}
	state = "waiting for svg";
	askForSVG();
	if ( state === "waiting for svg" ) {
		// In the event the parent frame hasn't loaded our handler yet.
		intervalId = setInterval( askForSVG, 300 );
	}
} else {
	doError( "Error: Cannot communicate with parent window" );
}

var startLua = function ( module, moduleName, entryFuncName, args ) {
	var initLua = `
		local init = {}
		function init.entryPoint( cb, args )
			local js = require "js"
			local svg = js.global.document:querySelector( 'body > svg' )
			cb( svg, args )
		end
		return init
	`;

	var init = fengari.load( initLua, "init" )();
	var module = fengari.load( module )();
	var func = module.get( entryFuncName );
	if ( !func ) {
		doError( "Error: Cannot find lua func " + entryFuncName );
	}
	var entryPoint = init.get( 'entryPoint' )
	r = entryPoint.invoke( func, [ args ] )
}

} )();
