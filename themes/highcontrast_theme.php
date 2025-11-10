<?php
// High Contrast Theme
$email_body = "
<html>
<head>
  <style>
  :root{
  --color:#000;
  --bg:transparent;
  --hcolor:#fff;
  --h-primary-color: #000; /* Top color variable */
  --h-secondary-color: #0f0f0f; /* Bottom color variable */
  --h-gradient: linear-gradient(to bottom, var(--h-primary-color), var(--h-secondary-color)); /* Define a gradient variable using the color variables */
  --border:#000;
  --lborder:#e5e5e5;
  --c-primary-color: #006060; /* Top color variable */
  --c-secondary-color: #008b8b; /* Bottom color variable */
  --c-gradient: linear-gradient(to bottom, var(--c-primary-color), var(--c-secondary-color)); /* Define a gradient variable using the color variables */
  --rowodd:#fff;
  --rocolor:#000;
  --roweven:#000;
  --recolor:#fff;
  --fcolor:#fff;
  --f-primary-color: #0f0f0f; /* Top color variable */
  --f-secondary-color: #000; /* Bottom color variable */
  --f-gradient: linear-gradient(to bottom, var(--f-primary-color), var(--f-secondary-color)); /* Define a gradient variable using the color variables */
}
    body {
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: var(--bg);
      color: var(--color);
      text-decoration: none !important;
    }
    .container {
      max-width: 700px;
      margin: 30px auto;
      background: var(--c-gradient);
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7);
      overflow: hidden;
      text-decoration: none !important;
    }
    .header {
      background: var(--h-gradient);
      color: var(--hcolor);
      padding: 28px 32px;
      text-align: center;
      font-size: 26px;
      font-weight: bold;
      text-shadow: 0 2px 6px rgba(0, 0, 0, 0.4);
      letter-spacing: 1px;
      user-select: none;
      text-decoration: none !important;
    }
	table {
	  border-collapse: collapse;
	  border-style: solid; /* Set a style for all borders */
	  border-width: 2px; /* Set a width for all borders */
	  border-color: var(--border) var(--border) var(--bg) var(--border); /* top right bottom left */
      text-decoration: none !important;
	}
	table td,
	table th {
	  border: 1px solid var(--lborder) !important;
	}
	table tr:nth-child(odd) {
	  background-color: var(--rowodd);
      color: var(--rocolor);
	}
	table tr:nth-child(even) {
	  background-color: var(--roweven);
      color: var(--recolor);
	}
    .footer {
      background: var(--f-gradient);
      color: var(--fcolor);
      font-size: 13px;
      padding: 18px 28px;
      text-align: center;
      user-select: none;
    }
  </style>
</head>
<body>
 <br />
  <div class='container'>
<table width=700px; cellpadding='10'>
<tr class='header'><th align='center' colspan='2'><a href='".$site_url."'><img src='". $site_url.$site_logo ."' align='left' height='25px'></a><font size='4'><strong>" . $form_title . " " . $form_name . "</strong></font></th></tr>
<tr><td width=22%><strong>Name:</strong></td><td>" . clean_string($name) . "</td></tr>
<tr><td width=22%><strong>Email / IP Address: </strong></td><td>" . clean_string($email) . " <strong>/</strong> (<strong>" . $user_ip . "</strong>)</td></tr>
<tr><td width=22%><strong><u>Subject</u>: </strong></td><td><strong><u>" . clean_string($subject) . "</u></strong></td></tr>
<tr><td width=22% valign='top'><strong>Message: </strong></td><td>" . clean_string($message) . "</td></tr>
<tr class='footer'><td align='right' colspan='2'><a href='". $site_url . "' style='color: var(--fcolor); text-decoration: none'>" . $site_name . "</a></td></tr>
</table>
</div>
</body>
</html>
";
?>
