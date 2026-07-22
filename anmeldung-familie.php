<?php
/**
 * Familien- / Sammelanmeldung (RaceResult Sammel-Formular, eingebettet)
 */
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Familien- & Sammelanmeldung | ATSV Marktlauf Kirchseeon 2026</title>
    <meta name="description" content="Melde mehrere Personen oder deine ganze Familie in einem Vorgang für den Marktlauf Kirchseeon 2026 an.">
    <?php require_once __DIR__ . '/src/layout/head.php'; ?>
</head>
<body>
    <?php require_once __DIR__ . '/src/layout/header.php'; ?>

    <main>
        <section id="anmeldung" class="section-padding" style="background-color: var(--gray-50);">
            <div class="container">
                <h2 class="text-center">Familien- &amp; Sammelanmeldung</h2>
                <p class="text-center" style="max-width: 680px; margin: -0.5rem auto var(--space-lg); color: var(--gray-600);">
                    Melde mehrere Personen &ndash; z.&nbsp;B. deine ganze Familie &ndash; in einem einzigen Vorgang an.
                    Die Online-Anmeldung ist ab 23.07., 12:00 Uhr ge&ouml;ffnet.
                </p>
                <p class="text-center" style="margin-bottom: var(--space-lg);">
                    <a href="index.html#anmeldung">&larr; Zur Einzelanmeldung</a>
                </p>

                <div class="registration-container">
                    <!-- RaceResult Sammel-Anmeldung Snippet Start -->
                    <script type="text/javascript">
                    <!--
                        var RRReg_eventid="412617";
                        var RRReg_name="Sammel-Anmeldung 2026";
                        var RRReg_key="i9Y2QolpQVAk";
                        var RRReg_server="https://events2.raceresult.com";
                    -->
                    </script>
                    <script type="text/javascript" src="https://events2.raceresult.com/registrations/init.js?lang=de-de"></script>
                    <style>
                        /* Eigenes CSS zum Anpassen der RaceResult-Anmeldung hier einfuegen */
                    </style>
                    <!-- RaceResult Sammel-Anmeldung Snippet End -->
                    <!-- Deutsches Datum in der RR-Vor-Oeffnungs-Meldung (ISO -> lesbar) -->
                    <script>
                    (function () {
                        var MON = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
                        var TAG = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
                        var ISO = /\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:[+-]\d{2}:\d{2}|Z)?/;
                        function fmt(iso){ var d=new Date(iso); if(isNaN(d.getTime())) return null;
                            var h=('0'+d.getHours()).slice(-2), m=('0'+d.getMinutes()).slice(-2);
                            return TAG[d.getDay()]+', '+d.getDate()+'. '+MON[d.getMonth()]+' '+d.getFullYear()+' um '+h+':'+m+' Uhr'; }
                        function fix(root){ if(!root) return;
                            var w=document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null), n;
                            while((n=w.nextNode())){ var m=n.nodeValue.match(ISO); if(m){ var s=fmt(m[0]);
                                if(s){ n.nodeValue=n.nodeValue.replace(m[0], s).replace(/\.\s*$/, ''); } } } }
                        var t=document.getElementById('anmeldung')||document.body;
                        fix(t);
                        new MutationObserver(function(ms){ ms.forEach(function(){ fix(t); }); })
                            .observe(t,{childList:true,subtree:true,characterData:true});
                    })();
                    </script>
                </div>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/src/layout/footer.php'; ?>
</body>
</html>
