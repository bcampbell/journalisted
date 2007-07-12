
function check_login_password_radio() {
    if (!document || !document.getElementById) return
    d = document.getElementById('loginradio2')
    if (!d) return
    d.checked = true
}

