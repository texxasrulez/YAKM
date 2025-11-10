# Multiple Recipients

1. In Admin → Settings → Recipients JSON, provide:
   ```json
   [
     { "label": "Sales", "email": "sales@example.com" },
     { "label": "Support", "email": "support@example.com" }
   ]
   ```
2. Add in your public page `<head>`:
   ```html
   <script src="kontact/assets/js/multiple_recipients.js"></script>
   ```
3. Ensure your form includes:
   ```html
   <select id="recipient" name="recipient" required></select>
   ```
The script populates options from the JSON at runtime.
