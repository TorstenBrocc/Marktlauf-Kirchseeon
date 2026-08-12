<!--
Master-Template für den Newsletter-Versand (E-Mail-tauglich, Tabellen-Layout, Inline-Styles).
Platzhalter: {{TITLE}} = Titel/Betreff (Hero-Headline), {{CONTENT}} = HTML-Body (vom LLM erzeugt).
Rahmen bleibt fix (keine LLM-Kontrolle über das Layout). Marken-Look: weiß + Schatten (nicht Creme),
Hero-Grün mit Volltonfallback, Orange nur als CTA. Abmeldelink + Pflicht-Postanschrift ergänzt Brevo beim Versand automatisch (aus dem Brevo-Firmenprofil).
-->
<!DOCTYPE html>
<html lang="de" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="color-scheme" content="light dark" />
<meta name="supported-color-schemes" content="light dark" />
<title>{{TITLE}} — Marktlauf Kirchseeon</title>
<!--[if mso]>
<style type="text/css">
  body, table, td, a { font-family: 'Segoe UI', Tahoma, Arial, sans-serif !important; }
</style>
<![endif]-->
<style type="text/css">
  @media only screen and (max-width: 600px) {
    .container { width: 100% !important; }
    .pad { padding-left: 22px !important; padding-right: 22px !important; }
    .h1 { font-size: 28px !important; line-height: 32px !important; }
  }
</style>
</head>
<body style="margin:0; padding:0; background-color:#eceee9; color:#1f2a22; -webkit-font-smoothing:antialiased;">

<span style="display:none; font-size:1px; color:#eceee9; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">Neues vom Marktlauf Kirchseeon — Infos, Termine und alles rund um den Lauf.</span>

<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#eceee9" style="background-color:#eceee9;">
<tr>
<td align="center" valign="top" style="padding:24px 12px 40px 12px;">

  <table role="presentation" class="container" width="600" border="0" cellspacing="0" cellpadding="0" style="width:600px; max-width:600px; background-color:#ffffff; border:1px solid #e3e6e0; border-radius:14px; box-shadow:0 6px 24px rgba(31,42,34,0.10); overflow:hidden;">

    <!-- Kopf: Wortmarke auf Weiß -->
    <tr>
      <td class="pad" align="center" bgcolor="#ffffff" style="background-color:#ffffff; padding:28px 32px 22px 32px;">
        <img src="https://atsv-kirchseeon-marktlauf.de/assets/images/marktlauf-wordmark.png" alt="Marktlauf Kirchseeon" width="196" style="display:block; width:196px; max-width:70%; height:auto; border:0;" />
      </td>
    </tr>

    <!-- Hero: Grünfläche mit Verlauf (Volltonfarbe als Fallback) -->
    <tr>
      <td class="pad" bgcolor="{{token:--color-primary}}" style="background-color:{{token:--color-primary}}; background-image:linear-gradient(128deg,{{token:--hero-gradient-start}} 0%,{{token:--hero-gradient-mid}} 55%,{{token:--hero-gradient-end}} 100%); padding:34px 40px 30px 40px;">
        <p style="margin:0 0 12px 0; font-family:'Segoe UI',Tahoma,Arial,sans-serif; font-size:12px; font-weight:bold; letter-spacing:2px; text-transform:uppercase; color:#f4fbe6; mso-line-height-rule:exactly; line-height:16px;">Marktlauf Kirchseeon</p>
        <h1 class="h1" style="margin:0; font-family:'Trebuchet MS',Verdana,Arial,sans-serif; font-size:34px; font-weight:bold; color:#ffffff; mso-line-height-rule:exactly; line-height:38px;">{{TITLE}}</h1>
      </td>
    </tr>

    <!-- Inhalt (LLM-erzeugt) -->
    <tr>
      <td class="pad" bgcolor="#ffffff" style="background-color:#ffffff; padding:32px 40px 34px 40px; font-family:'Segoe UI',Helvetica,Arial,sans-serif; font-size:16px; color:#475569; mso-line-height-rule:exactly; line-height:26px;">
        {{CONTENT}}
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td class="pad" align="center" bgcolor="#1f2a22" style="background-color:#1f2a22; padding:26px 40px 28px 40px;">
        <img src="https://atsv-kirchseeon-marktlauf.de/assets/images/ATSV_Logo-750x968.png" alt="ATSV Kirchseeon 1906 e.V." width="38" style="display:block; margin:0 auto 12px auto; width:38px; height:auto; border:0;" />
        <p style="margin:0 0 14px 0; font-family:'Segoe UI',Helvetica,Arial,sans-serif; font-size:13px; font-weight:bold; color:#eafbe4; mso-line-height-rule:exactly; line-height:20px;">ATSV Kirchseeon 1906 e.V.</p>
        <p style="margin:0; font-family:'Segoe UI',Helvetica,Arial,sans-serif; font-size:12px; color:#a3b3a6; mso-line-height-rule:exactly; line-height:19px;">
          <a href="https://atsv-kirchseeon-marktlauf.de" target="_blank" style="color:{{token:--hero-gradient-end}}; text-decoration:none;">Website</a>
          &nbsp;·&nbsp;
          <a href="https://atsv-kirchseeon-marktlauf.de/impressum.html" target="_blank" style="color:{{token:--hero-gradient-end}}; text-decoration:none;">Impressum</a>
          &nbsp;·&nbsp;
          <a href="https://atsv-kirchseeon-marktlauf.de/datenschutz.html" target="_blank" style="color:{{token:--hero-gradient-end}}; text-decoration:none;">Datenschutz</a>
        </p>
      </td>
    </tr>

  </table>

</td>
</tr>
</table>

</body>
</html>
