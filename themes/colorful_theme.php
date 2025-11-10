<?php
// Colorful Classy Theme
$email_body = "
<html>
<head>
  <style>
  :root{
  --color:#4b245b;
  --bg:transparent;
  --hcolor:#fff;
  --h-primary-color: #4b245b; /* Top color variable */
  --h-secondary-color: #6c3483; /* Bottom color variable */
  --h-gradient: linear-gradient(to bottom, var(--h-primary-color), var(--h-secondary-color)); /* Define a gradient variable using the color variables */
  --border:#4b245b;
  --lborder:#6c3483;
  --c-primary-color: #006060; /* Top color variable */
  --c-secondary-color: #008b8b; /* Bottom color variable */
  --c-gradient: linear-gradient(to bottom, var(--c-primary-color), var(--c-secondary-color));
  --r4color: #154360;
  --fcolor: #fff;
  --f-primary-color: #6c3483; /* Top color variable */
  --f-secondary-color: #4b245b; /* Bottom color variable */
  --f-gradient: linear-gradient(to bottom, var(--f-primary-color), var(--f-secondary-color));
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
      background: var(--h-gradient) !important;
      color: var(--hcolor) !important;
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
    .footer {
      background: var(--f-gradient) !important;
      color: var(--fcolor) !important;
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

<tr style='background: #FADBD8; color: #5B2C6F;'>
<td width='22%'><strong>Name:</strong></td><td>" . clean_string($name) . "</td></tr>

<tr style='background: #D5F5E3; color: #1E8449;'>
<td><strong>Email / IP Address:</strong></td>
<td>" . clean_string($email) . " <strong>/</strong> (<strong>" . $user_ip . "</strong>)</td></tr>

<tr style='background: #FCF3CF; color: #9A7D0A;'>
<td><strong><u>Subject</u>:</strong></td><td><strong><u>" . clean_string($subject) . "</u></strong></td></tr>

<tr style='background: #D6EAF8; color: #154360;' valign='top'>
<td><strong>Message:</strong></td><td>" . clean_string($message) . "</td></tr>
<tr class='footer'><td align='right' colspan='2'><a href='". $site_url . "' style='color: var(--fcolor); text-decoration: none'>" . $site_name . "</a></td></tr>
</table>
</div>
</body>
</html>
";
?>
