( function () {
	document.querySelectorAll( '.culprit-finder-copy' ).forEach( function ( button ) {
		var target = document.getElementById( button.getAttribute( 'data-target' ) );
		if ( ! target || ! navigator.clipboard ) {
			return;
		}
		button.hidden = false;
		button.addEventListener( 'click', function () {
			var text = 'value' in target ? target.value : target.textContent;
			navigator.clipboard.writeText( text ).then( function () {
				var label = button.textContent;
				button.textContent = button.getAttribute( 'data-done' );
				setTimeout( function () {
					button.textContent = label;
				}, 2000 );
			}, function () {
				var range = document.createRange();
				range.selectNodeContents( target );
				window.getSelection().removeAllRanges();
				window.getSelection().addRange( range );
			} );
		} );
	} );
} )();

( function () {
	document.querySelectorAll( '.cf-setup' ).forEach( function ( form ) {
		form.classList.add( 'cf-js' );
		var box = form.querySelector( 'input[name="culprit_finder_saved"]' );
		var start = form.querySelector( '.cf-start button[type="submit"]' );
		var help = form.querySelector( '.cf-start__help' );
		if ( ! box || ! start || start.disabled ) {
			return;
		}
		var text = help ? help.textContent : '';
		var sync = function () {
			start.disabled = ! box.checked;
			if ( help ) {
				help.textContent = box.checked ? text : help.getAttribute( 'data-locked' );
			}
		};
		box.addEventListener( 'change', sync );
		sync();
	} );
} )();
