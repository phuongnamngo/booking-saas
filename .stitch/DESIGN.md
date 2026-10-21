# Design System: Sổ lịch spa
**Project ID:** 12809359952938126879

## 1. Visual Theme & Atmosphere
A Vietnamese spa and salon reception desk, not a marketing landing page. The room is quiet mineral paper with deep pine ink. Density is comfortable for a front desk: enough air to scan a day of appointments, tight enough that a manager sees the next guest without scrolling past decoration. One thing carries the visual weight: the appointment time, set large and calm. Everything else stays secondary. No gradient mesh, no all-caps eyebrows, no identical card grid, no cream-and-terracotta palette.

## 2. Color Palette & Roles
- **Mineral paper** (#F4F7F6): page background. A cool, slightly green paper so the desk does not read as warm cream.
- **Card white** (#FBFCFB): panels, forms, and the booking sheet.
- **Pine ink** (#1A2E2A): headings, guest names, and the large appointment time.
- **Moss mute** (#5E6F6A): helper text, step labels, and table meta.
- **Verdigris stamp** (#0F6E56): the single action color. Primary buttons and the selected time chip only.
- **Stamp wash** (#E7F3EE): selected row, selected step, and the success strip after a booking.
- **Hairline** (#D7E0DC): borders and dividers. One pixel, never a heavy frame.
- **Cancel brick** (#8E3B3B): destructive text and the cancel action only. Never a brand color.

## 3. Typography Rules
Be Vietnam Pro for both headlines and body so Vietnamese diacritics stay intact. Headlines are semibold (600) with tight tracking (-0.02em) and a line height near 1.2. Body is regular (400) at 16px with a line height of 1.5. The appointment time uses the same family at 32px semibold, pine ink, no letter-spacing tricks. Labels are sentence case. Do not set navigation or field labels in all capitals.

## 4. Component Stylings
* **Buttons:** Subtly rounded corners (8px). Primary is verdigris stamp with card-white text. Secondary is card white with a hairline and pine ink text. The cancel action is text-only in cancel brick. One primary button per view.
* **Cards/Containers:** The booking sheet and the day list use card white, a hairline, and a whisper-soft shadow (0 8px 24px rgba(26, 46, 42, 0.06)). Corners are gently rounded (16px) on the booking sheet only. Tables and lists stay flatter, with 8px corners, so the sheet is the one elevated object.
* **Inputs/Forms:** Hairline stroke, card-white fill, 8px corners, 44px height. Focus ring is a 2px verdigris stamp outline with 2px offset. Placeholder is moss mute.
* **Time chips:** Quiet chips with a hairline. The chosen chip fills with stamp wash and a verdigris stamp border. The time itself is the label, not an icon.
* **Navigation:** A slim left rail on desktop, pine ink on mineral paper, the current item marked with a stamp wash. No icon rainbow. Mobile public booking has no rail: a short step line above the sheet.

## 5. Layout Principles
Left-aligned, 8px spacing rhythm. Desktop admin is a rail (220px) plus a main column with a 32px page inset. The public booking page is one column on a phone (16px inset) and, on a wide screen, the step sheet on the left with the chosen-time card pinned on the right. Do not center a marketing hero. Do not wrap every field in its own card. Group fields inside one sheet. Status words (Chờ xác nhận, Đã xác nhận, Đã hủy) sit inline beside the guest name, colored with moss mute unless the row is cancelled, which uses cancel brick.
