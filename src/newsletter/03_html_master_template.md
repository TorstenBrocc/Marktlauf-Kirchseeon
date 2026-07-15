<!--
Master-Template für den Newsletter-Versand (E-Mail-tauglich, Inline-Styles).
Platzhalter: {{TITLE}} = Titel/Betreff, {{CONTENT}} = HTML-Body (vom LLM erzeugt).
Wird serverseitig gefüllt; der Rahmen bleibt fix (keine LLM-Kontrolle über das Layout).
-->
<div style="margin:0;padding:0;background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#333;">
  <div style="max-width:600px;margin:0 auto;background:#ffffff;">
    <div style="background:#009640;padding:24px 32px;">
      <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">{{TITLE}}</h1>
      <p style="margin:4px 0 0;color:#e8f5e9;font-size:13px;">Marktlauf Kirchseeon · ATSV Kirchseeon e.V.</p>
    </div>
    <div style="padding:28px 32px;font-size:15px;line-height:1.6;">
      {{CONTENT}}
    </div>
    <div style="padding:20px 32px;border-top:1px solid #e5e5e5;font-size:12px;color:#888;">
      <p style="margin:0;">ATSV Kirchseeon e.V. · <a href="https://atsv-kirchseeon-marktlauf.de" style="color:#009640;">atsv-kirchseeon-marktlauf.de</a></p>
    </div>
  </div>
</div>
