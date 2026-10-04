/* Culprit Finder: Copy buttons. Without JS everything stays selectable. */
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

/* Start stays disabled until "I've saved both links" is ticked (the checkbox is also `required`). */
( function () {
	document.querySelectorAll( '.cf-setup' ).forEach( function ( form ) {
		var box = form.querySelector( 'input[name="culprit_finder_saved"]' );
		var start = form.querySelector( '.cf-start button[type="submit"]' );
		if ( ! box || ! start || start.disabled ) {
			return;
		}
		var sync = function () {
			start.disabled = ! box.checked;
		};
		box.addEventListener( 'change', sync );
		sync();
	} );
} )();
