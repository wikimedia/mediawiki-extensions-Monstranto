$( function () {

	var sendError = function ( window, error ) {
		window.postMessage( {
			msg: error,
			monstranto: "client",
			type: "error"
		}, '*' );
	}

	window.addEventListener( 'message', function ( event ) {
		// Note the iframe has a "null" origin.
		if ( event.data.monstranto !== 'server' ) {
			// not intended for us.
			return;
		}

		if ( !event.data.id.match( /^[a-zA-Z0-9_-]+$/ ) ) {
			console.log( "Monstranto invalid id" );
			return;
		}

		// MediaWiki prevents users from making attributes prefiex with data-mw.
		// As a precaution, we always check that the data-mw-monstranto attribute is present.
		var iframe = document.querySelector(
			'iframe#mw-monstranto-frame-' + event.data.id + '[data-mw-monstranto]'
		);
		if ( !iframe ) {
			console.log( "Could not locate monstranto frame" );
			return;
		}
		// Don't rely on event.source directly, since we can't verify origin.
		if ( iframe.contentWindow !== event.source ) {
			console.log( "iframe window does not match source window!!!" );
			return;
		}

		if ( event.origin !== 'null' ) {
			console.log( "Expected monstranto events to come from null origin" );
			return;
		}
		switch ( event.data.type ) {
			case 'getSVG':
				opts = JSON.parse( iframe.getAttribute( 'data-mw-monstranto' ) );
				var sendSvg = function ( svgText ) {
					iframe.contentWindow.postMessage(
						{
							monstranto: 'client',
							type: 'setSVG',
							svg: svgText,
							activation: opts.activation
						},
						'*'
					);
				}
				if ( opts.svgFile !== undefined ) {
					fetch( opts.svgFile, { mode: 'cors' } )
						.then(function ( res ) {
							if ( !res.ok ) {
								throw new Error( "fetch error" );
							}
							return res.text()
						} )
						.then(sendSvg)
						.catch( function( e ) {
							console.log( e );
							sendError( iframe.contentWindow, "Could not fetch svg" )
						} );
				} else {
					sendSvg( opts.svgText );
				}
				break;
			case 'activate':
				opts = JSON.parse( iframe.getAttribute( 'data-mw-monstranto' ) );
				if ( opts.activationCallback ) {
					api = new mw.Api();
					api.get( {
						'action': 'query',
						'prop': 'revisions',
						'rvslots': 'main',
						'rvprop': 'content',
						'titles': 'Module:' + opts.activationCallback[0],
						'formatversion': 2
					} ).done( function ( data ) {
						if (
							!data.query ||
							!data.query.pages ||
							!data.query.pages[0] ||
							!data.query.pages[0].revisions ||
							!data.query.pages[0].revisions[0] ||
							!data.query.pages[0].revisions[0].slots ||
							!data.query.pages[0].revisions[0].slots.main ||
							!data.query.pages[0].revisions[0].slots.main.content ||
							data.query.pages[0].revisions[0].slots.main.contentmodel !== 'Scribunto'
						) {
							sendError(
								iframe.contentWindow,
								mw.msg( "monstranto-lua-notfound", 'Module:' + opts.activationCallback[0] )
							);
							return;
						}
						iframe.contentWindow.postMessage( {
							monstranto: 'client',
							type: 'activate',
							moduleName: data.query.pages[0].title,
							moduleText: data.query.pages[0].revisions[0].slots.main.content,
							callback: opts.activationCallback[1],
							callbackParameter: opts.callbackParameter
						}, '*' );
					} );
				} else {
					iframe.contentWindow.postMessage( {
						monstranto: 'client',
						type: 'activate-nocallback',
					}, '*' );
				}
				break;
			default:
				console.log( "Monstranto: Unrecognized message type" );

		}
	} );
} )
