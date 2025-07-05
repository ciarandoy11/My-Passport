<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Sign Up</title>
        <link rel="stylesheet" href="/style.css">
        <link rel="icon" href="./favicon.ico" type="image/x-icon">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
        <style>
  /* RESET */
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    scroll-behavior: smooth;
  }

  body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #89f7fe, #66a6ff);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow-x: hidden;
    animation: backgroundAnimation 10s infinite alternate ease-in-out;
  }

  @keyframes backgroundAnimation {
    0% { background-position: left; }
    100% { background-position: right; }
  }

  main {
    background: white;
    padding: 2rem;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    width: 90%;
    max-width: 400px;
    text-align: center;
    animation: fadeInMain 1.5s ease;
  }

  @keyframes fadeInMain {
    0% { opacity: 0; transform: scale(0.9); }
    100% { opacity: 1; transform: scale(1); }
  }

  h1 {
    font-size: 2.2rem;
    margin-bottom: 1.5rem;
    color: #333;
    animation: pulse 2s infinite alternate;
  }

  @keyframes pulse {
    0% { transform: scale(1); }
    100% { transform: scale(1.05); }
  }

  form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  input[type="text"],
  input[type="password"],
  input[type="email"] {
    padding: 0.8rem;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 1rem;
    transition: all 0.3s ease;
  }

  input[type="text"]:hover,
  input[type="password"]:hover,
  input[type="email"]:hover {
    border-color: #007BFF;
    box-shadow: 0 0 5px rgba(0,123,255,0.5);
  }

  input[type="text"]:focus,
  input[type="password"]:focus,
  input[type="email"]:focus {
    outline: none;
    border-color: #0056b3;
    box-shadow: 0 0 10px rgba(0,86,179,0.6);
  }

  button {
    padding: 0.8rem;
    border-radius: 8px;
    border: none;
    background: linear-gradient(135deg, #007BFF, #0056b3);
    color: white;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 0.5rem;
  }

  button:hover {
    background: linear-gradient(135deg, #0056b3, #004494);
    transform: scale(1.05);
  }

  button[onclick] {
    background: linear-gradient(135deg, #28a745, #218838);
  }

  button[onclick]:hover {
    background: linear-gradient(135deg, #218838, #1e7e34);
  }

  #livesearch {
    max-height: 200px;
    overflow-y: auto;
    background: white;
    position: absolute;
    width: 100%;
    max-width: 360px;
    z-index: 200;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    animation: fadeIn 0.5s ease;
  }

  #livesearch div {
    padding: 10px;
    cursor: pointer;
    transition: background 0.3s;
  }

  #livesearch div:hover {
    background: #f1f1f1;
  }

  label {
    margin-top: 1rem;
    font-size: 0.9rem;
    animation: floatLabel 3s infinite alternate ease-in-out;
  }

  @keyframes floatLabel {
    0% { transform: translateY(0); }
    100% { transform: translateY(-5px); }
  }

  /* New style to center the user form */
    #userForm {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    justify-content: center;
    align-items: center;
    transform: translate(16%, 0%);
    top: 50%;
    left: 50%;
    width: 90%; /* Ensuring it fits within the container */
    max-width: 400px;
    animation: form 1.5s ease;
    }

    /* Reuse your existing fadeInMain animation */
    @keyframes form {
    0% { opacity: 0; transform: translate(0%, 0%); }
    100% { opacity: 1; transform: translate(16%, 0%); }
    }

  /* Minor animations on scroll */
  @media (prefers-reduced-motion: no-preference) {
    input, button {
      will-change: transform;
    }
    input:focus, button:focus {
      animation: bounce 0.3s ease forwards;
    }
  }

  @keyframes bounce {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
  }

</style>
    </head>
    <body>
        <main>
            <h1>Sign Up</h1>
            <form method="POST" action="checkout.php">
                <label for="club">Club Name: </label>
                <input type="text" id="club" name="club" placeholder="Club Name" required pattern="[A-Za-z0-9\s]+" title="Only alphanumeric characters and spaces are allowed" oninput="this.value = this.value.replace(/[^A-Za-z0-9\s]+/g, '');">

                <button type="button" onclick="document.getElementById('userForm').style.display='block';" name="createUser" style="background-color: green; color: white; border: none; padding: 10px 20px; font-size: 16px; border-radius: 5px; cursor: pointer; transition: background-color 0.3s ease;">Create Club + Admin account</button>

                <div id="userForm" style="display:none;">
                    <input type="text" name="first_name" placeholder="First Name" required pattern="[A-Za-z\s]+" title="Only alphabetic characters and spaces are allowed" oninput="this.value = this.value.replace(/[^A-Za-z\s]+/g, '');">
                    <input type="text" name="last_name" placeholder="Last Name" required pattern="[A-Za-z\s]+" title="Only alphabetic characters and spaces are allowed" oninput="this.value = this.value.replace(/[^A-Za-z\s]+/g, '');">
                    <input type="text" name="username" placeholder="Admin Username" required pattern="[A-Za-z0-9]+" title="Only alphanumeric characters are allowed" oninput="this.value = this.value.replace(/[^A-Za-z0-9]+/g, '');">
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="text" name="phone" placeholder="Phone" required pattern="[0-9]+" title="Only numeric characters are allowed" oninput="this.value = this.value.replace(/[^0-9]+/g, '');">
                    <input type="password" name="password" placeholder="Password">
                    <div style="transform: translate(-10%, 0);">
                        <p>Leave Blank for default Password.</p>
                        <p>Default Password is: VerySecretPW1234</p>
                        <button type="submit">Finish</button>
                    </div>
                </div>
            </form>
        </main>
    </body>
</html>
