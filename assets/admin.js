/* Culprit Finder: Copy report button. The report stays selectable without JS. */
( function () {
	document.querySelectorAll( '.culprit-finder-copy' ).forEach( function ( button ) {
		var target = document.getElementById( button.getAttribute( 'data-target' ) );
		if ( ! target || ! navigator.clipboard ) {
			return;
		}
		button.hidden = false;
		button.addEventListener( 'click', function () {
			navigator.clipboard.writeText( target.value ).then( function () {
				var label = button.textContent;
				button.textContent = button.getAttribute( 'data-done' );
				setTimeout( function () {
					button.textContent = label;
				}, 2000 );
			}, function () {
				target.focus();
				target.select();
			} );
		} );
	} );
} )();
