
function check_login_password_radio() {
    if (!document || !document.getElementById) return
    d = document.getElementById('loginradio2')
    if (!d) return
    d.checked = true
}

function gen_mailto()
{
	var i = arguments.length-1;
	var addr = arguments[ i ] + '@';
	i--;

	while( i>=2 )
	{
		addr += arguments[i] + '.';
		i--;
	}
	addr += arguments[1];
	text = arguments[0];
	if( text == '' )
	{
		text = addr;
	}

	document.write( '<a href="mai' );
	document.write( 'lto:' + addr + '">' + text + '</a>' );
}

