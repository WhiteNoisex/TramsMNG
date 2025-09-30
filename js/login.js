const loginForm = document.getElementById('loginForm');
const loginError = document.getElementById('loginError');
const masterServer = "https://web.interfacetools.com/TramsMNG";

loginForm.addEventListener('submit', function(e) {
  e.preventDefault();
  loginError.textContent = "";
  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value;
  authenticateUser(username,password);
});


String.prototype.hashCode = function() {
    var hash = 0, i, chr;
    if (this.length === 0) return hash;
    for (i = 0; i < this.length; i++) {
        chr   = this.charCodeAt(i);
        hash  = ((hash << 5) - hash) + chr;
        hash |= 0; // Convert to 32bit integer
    }
    return hash;
};



async function hash(string) {
    const utf8 = new TextEncoder().encode(string);
    const hashBuffer = await crypto.subtle.digest('SHA-256', utf8);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    const hashHex = hashArray
      .map((bytes) => bytes.toString(16).padStart(2, '0'))
      .join('');
    return hashHex;
  }
  
async function authenticateUser(Username, Password) {
    if(Password !== "ChangeMe"){
        Password = await hash(Password);
    }
    
    try {
        const response = await fetch(masterServer + '/api/users_API_578451Gbygb.php', {
            method: 'post',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                username: Username,
                password: Password
            })
        });

        const responseData = await response.json();

        if (response.ok) {
            console.log(responseData.message); // Success message

            // Save Token And username In Session Storage
            sessionStorage.setItem("username",Username);
            sessionStorage.setItem("token",responseData.token);

            window.location.replace('?A=logged.html');
            // Add any further actions for successful authentication
        } 
        else if(response.status === 401)
        {
            console.log('Incorect Detials')
            loginError.textContent = 'Incorrect Username or Password!';
        }
        else {
            console.error(responseData.error); // Error message
            // Add any further error handling
        }
    } catch (error) {
        console.error('Error:', error);
        // Add any further error handling
    }
}
