# Active Task: Fix Newsletter AdBlocker Issue

## Goal
Implement user-friendly error handling for the Brevo newsletter registration to notify users when an AdBlocker is preventing the submission.

## Status
- [x] Analyze newsletter form structure in `index.html`.
- [x] Implement `initNewsletterForm` in `js/main.js` to intercept form submission.
- [x] Add `fetch` call with error handling to detect blocked requests (Catch-Error).
- [x] Display a specific warning message: "Hinweis: Die Anmeldung wurde blockiert. Bitte deaktiviere deinen AdBlocker und versuche es erneut."
- [x] Ensure redirection to `newsletter-bestaetigung.html` only occurs on a successful (200/201) response.
- [x] Commit changes to Git.

## Outcome
The newsletter registration now provides a clear hint to users if their AdBlocker is interfering, and the redirection to the confirmation page is only triggered upon a successful API response, improving the user experience and reliability.