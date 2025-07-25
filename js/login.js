const loginForm = document.getElementById('loginForm');
const loginError = document.getElementById('loginError');
var masterServer = "http://localhost/tramsMNG"

loginForm.addEventListener('submit', function(e) {
  e.preventDefault();
  loginError.textContent = "";
  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value;
  authenticateUser(username,password);
});

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
