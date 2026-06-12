# Tech Stack: ATSV Kirchseeon Marktlauf

## Frontend
- **HTML5**: Semantic structure for the landing page.
- **CSS3**: 
    - Modular architecture: `base.css` (resets/vars), `layout.css` (header/footer/grid), `components.css` (UI elements).
    - Custom Properties (CSS Variables) for colors and spacing.
    - Flexbox and Grid for responsive layout.
- **JavaScript (Vanilla)**: 
    - Mobile menu toggle.
    - Smooth scrolling for anchor links.
    - Map lifecycle management.

## Backend
- **PHP**: `contact.php` for server-side email processing (configured for Strato hosting).
- **Email Delivery**: Native PHP `mail()` function.

## Assets & External Services
- **Google Maps**: Iframe embed for location.
- **GPX**: Route files for download.
- **Fonts**: Google Fonts (Inter).