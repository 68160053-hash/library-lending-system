# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack
Existing codebase: PHP 8 (procedural mysqli) + MySQL 8, plain CSS in `assets/css/app.css`, one small JS file `assets/js/site.js`, hosted on the university server (Angsila, `~68160053/library/`). No build step; files are uploaded as a zip.

## Users
Thai university students and young readers who want to read many books cheaply without buying and keeping them, plus general readers who compare rental price against purchase price. Mostly on a phone. They browse, pick a rental length (7/14/30 days), pay by transfer, and wait for a parcel. Staff (a few people) run the back office: confirm payment, ship with a tracking number, take returns.

## Product Purpose
Online book rental shop (course project for Database Management, Burapha University, milestone 2 due 10 Nov 2026). Customers rent books by the day, pay rent plus a refundable deposit, receive them by parcel, and return them. Success: a customer can find a book, see the true total price at once, order in a few taps and always know where the order is; staff can process an order in under a minute.

## Positioning
Rent by the day from a few baht, a deposit returned in full when the book comes back (only late fees are deducted), delivered home with a tracking number, and real covers and real descriptions for 60 curated books across fantasy, science, history, literature and computing.

## Operating Context
Orders move through unpaid, payment reported, payment confirmed, shipped, returned (or cancelled, or lost). Money is bank transfer reported by the customer and confirmed by staff; there is no payment gateway and no slip upload. Late fee = days late x daily rate, deducted from the deposit. Prices and stock are per book and copied into the rental at order time.

## Capabilities and Constraints
Catalogue with search, category filter and sort; book detail with 7/14/30 day price plans; cart (max 5 books); checkout with delivery address; order page with a status timeline and tracking number; renew once (+7 days); profile; staff dashboard, order desk, overdue list, book management, user roles; optional Google sign-in. Bank account details are optional and may be absent. Must keep every working flow and the 188-check test suite (`tests/shop.mjs`) passing; markup that tests read (form fields, `name="csrf"`, button values like `value="cancel"`) must keep working.

## Brand Commitments
Name: Book Lending Shop, Thai tagline: ร้านเช่าหนังสือออนไลน์. Thai-first copy, short and friendly, never childish. Sarabun (or another sharp Thai face) for reading. Must not look like a kids' site or a generic template. Must stay light and fast on a phone.

## Evidence on Hand
60 real book covers (`assets/covers/<isbn>.jpg`, mixed aspect ratios, some low resolution) with real descriptions from Open Library. No customer testimonials, no sales figures, no real shop photos exist; none may be invented. Rental prices and stock are demonstration values.

## Product Principles
1. The total price is shown before any commitment, always split into rent and refundable deposit.
2. Every order shows where it is and what happens next.
3. Light and quick on a phone beats clever on a desktop.
4. The covers are the shop's colour; the interface frames them.
5. Thai reads first: size, line height and contrast serve Thai text.

## Accessibility & Inclusion
WCAG AA contrast, visible focus, keyboard operable, no motion required to understand state, honour reduced-motion.
