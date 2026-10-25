# Design System: Sổ lịch Desk
**Project ID:** 15047864017730781680
**Design system asset:** assets/11799409904409379582 (version 2, FIDELITY, Be Vietnam Pro, ROUND_EIGHT)

## 1. Visual Theme & Atmosphere
A Vietnamese reception desk for salons and clinics. Calm, modern, paper-and-ink. Not a generic SaaS dashboard. One thing carries the visual weight on each screen: the appointment time on the desk, the big figure on reports, the chosen slot in the booking wizard. No gradient mesh, no all-caps eyebrows, no icon rainbow.

## 2. Color Palette & Roles
| Token | Hex | Role |
|---|---|---|
| `--paper` Paper Mist | #F4F7F6 | Page background |
| `--card` Warm Card | #FBFCFB | Panels, forms, sheets |
| `--ink` Ink | #1A2E2A | Headings, times, figures |
| `--mute` Moss Mute | #5E6F6A | Helper text, meta, step labels |
| `--stamp` Stamp Green | #0F6E56 | Primary buttons, current nav, links, chart bars |
| `--wash` Pale Mint | #E7F3EE | Selected rows, active step pill, current rail item, success toast |
| `--line` Leaf Line | #D7E0DC | 1px hairlines |
| `--brick` Brick | #8E3B3B | Destructive text buttons and error text only |

Status pills: pending uses amber, confirmed uses stamp on wash, in progress uses sky, cancelled and no-show use brick on brick wash, completed uses mute.

## 3. Typography Rules
Be Vietnam Pro everywhere so Vietnamese diacritics stay intact. Headlines weight 600, letter-spacing -0.02em. Page title 28px, section 18px, body 16px, label 14px weight 500. Appointment times 32px and metrics 40px with tabular numbers. Sentence case labels.

## 4. Component Stylings
* **Buttons:** 8px radius, 44px min height. Primary is stamp green with white text; secondary is card with a hairline; destructive is text-only brick. One primary per view. Press scales to 0.98.
* **Cards:** 16px radius, 1px Leaf Line border, shadow `0 8px 24px rgba(26,46,42,0.06)`.
* **Inputs:** hairline, card fill, 8px radius, 44px height, 2px stamp focus ring with 2px offset.
* **Pills:** fully rounded, dot plus label.
* **Side sheet:** right-anchored dialog for create and reschedule on the appointments page, scrim behind, Escape closes.
* **Toast:** drops in under the top bar; error variant in brick.

## 5. Layout Principles
* Signed-in desk: fixed 220px left rail with the "Sổ lịch" wordmark and role subtitle, a top bar with the notification bell, and a content column.
* Public and auth screens: centered single card on paper, max 28rem; the booking wizard is 32rem.
* Under 800px the rail becomes a horizontal wrapping nav.
* 8px spacing rhythm.

## 6. Motion
* The shell never moves on navigation. Only the page content enters: 12px rise plus fade, 280ms ease-out (`--ease-out: cubic-bezier(0.22, 1, 0.36, 1)`).
* Lists stagger 40ms per item through the `--i` custom property.
* Loading uses a skeleton shimmer.
* Wizard steps slide 40px horizontally with a cross-fade, direction-aware; the success state settles in with a drawn check mark.
* Side sheet slides in over 320ms.
* `prefers-reduced-motion: reduce` falls back to fade only.

## 7. Screens
Stitch exports live in `.stitch/designs/` (HTML and PNG per screen); see `metadata.json` for screen IDs. Implementation lives in `web/src/index.css` (tokens, base controls, keyframes) and `web/src/App.css` (layout and components).
