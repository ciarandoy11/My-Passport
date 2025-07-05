<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign Up</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/light.css">
  <link rel="icon" href="./favicon.ico" type="image/x-icon">
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

  <script>
    function showResult(str) {
      const swimmers = str.split(',').map(s => s.trim());
      const lastSwimmer = swimmers[swimmers.length - 1];

      if (lastSwimmer.length == 0) {
        document.getElementById("livesearch").innerHTML = "";
        document.getElementById("livesearch").style.border = "0px";
        return;
      }
      const xmlhttp = new XMLHttpRequest();
      xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          document.getElementById("livesearch").innerHTML = this.responseText;
          document.getElementById("livesearch").style.border = "1px solid #A5ACB2";
        }
      };
      xmlhttp.open("GET", "livesearch-club.php?q=" + lastSwimmer, true);
      xmlhttp.send();
    }

    function selectSuggestion(value) {
      const input = document.getElementById("club");
      const currentValue = input.value;
      const lastCommaIndex = currentValue.lastIndexOf(',');
      if (lastCommaIndex === -1) {
        input.value = value;
      } else {
        input.value = currentValue.substring(0, lastCommaIndex + 1) + ' ' + value;
      }
      document.getElementById("livesearch").innerHTML = "";
      document.getElementById("livesearch").style.border = "0px";
    }
  </script>
</head>

<body>
  <main>
    <h1>Sign Up</h1>
    <form method="POST" action="">
      <input type="text" name="first_name" placeholder="First Name" required>
      <input type="text" name="last_name" placeholder="Last Name" required>
      <input type="text" name="username" placeholder="Username" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="text" name="phone" placeholder="Phone" required>

      <div style="position: relative;">
        <input type="text" size="30" onkeyup="showResult(this.value)" id="club" name="club" placeholder="Club" required>
        <div id="livesearch"></div>
      </div>

      <input type="password" name="password" placeholder="Password" required>
      <button type="submit">Sign Up</button>
    </form>

    <label for="club-signup">Is your club not signed up <b>yet</b>? <br> Sign your club up here:</label>
    <button onclick="document.location='club-signup.php'">Club Sign up</button>
  </main>
</body>
</html>
