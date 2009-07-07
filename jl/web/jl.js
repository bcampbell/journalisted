
function check_login_password_radio() {
    if (!document || !document.getElementById) return
    d = document.getElementById('loginradio2')
    if (!d) return
    d.checked = true
}

/* first arg is text for the link, if empty string, the address is displayed.
 * Following args are parts of the address, in reverse. The last arg is the name.
 * eg: gen_mailto( 'email Fred', 'com','blah','fred bloggs');
 */
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


/* Safari supports a 'placeholder' attribute on text input elements
 * to display greyed-out text which disappears automatically.
 * This Fn sets up similar behaviour for non-Safari browsers.
 * By Jordan Harper,
 * http://www.beyondstandards.com/archives/input-placeholders/
 */
function activatePlaceholders() {
	var detect = navigator.userAgent.toLowerCase();
	if (detect.indexOf("safari") > 0) return false;
	var inputs = document.getElementsByTagName("input");
	for (var i=0;i<inputs.length;i++) {
		if (inputs[i].getAttribute("type") == "text") {
			if (inputs[i].getAttribute("placeholder") && inputs[i].getAttribute("placeholder").length > 0) {
				inputs[i].value = inputs[i].getAttribute("placeholder");
				inputs[i].onclick = function() {
					if (this.value == this.getAttribute("placeholder")) {
						this.value = "";
					}
					return false;
				}
				inputs[i].onblur = function() {
					if (this.value.length < 1) {
						this.value = this.getAttribute("placeholder");
					}
				}
			}
		}
	}
}

/*
 * fn to allow multiple window.onloads to be chained
 * from http://simonwillison.net/2004/May/26/addLoadEvent/
 */
function addLoadEvent(func) {
  var oldonload = window.onload;
  if (typeof window.onload != 'function') {
    window.onload = func;
  } else {
    window.onload = function() {
      if (oldonload) {
        oldonload();
      }
      func();
    }
  }
}

