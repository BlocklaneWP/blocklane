=== Blocklane ===
Contributors: garrettmichaelj
Tags: seo, contact form, popup, coming soon, security
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.11.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Everything a WordPress site should have out of the box: SEO, a contact form, popups, a coming soon page, and security hardening. In one plugin.

== Description ==

Blocklane gives a new WordPress site the things it needs on day one, without
installing six plugins to get them. Every tool is built on WordPress's own
blocks, settings and APIs — there is no page builder, no separate rendering
engine, and no proprietary markup.

Each tool is off until you turn it on. What you do not use costs nothing: no
files load, no hooks register, no queries run.

= SEO =

A search title, a meta description and a share image on every page and post,
with a live preview of the search result. The front end emits the matching
title tag, meta description, Open Graph tags and structured data, plus sitemap
controls. If a dedicated SEO plugin is active, Blocklane's output stands down
automatically so your tags are never doubled.

= Contact forms =

Build a form out of blocks — text, email, textarea, select, radio, checkbox —
with a real email notification and submissions stored in your own database.
Spam protection is included. No shortcodes, no separate form builder.

= Popups =

A popup built like any other page content, shown on a delay, everywhere or on
the pages and posts you choose.

= Coming soon, maintenance mode and passwords =

Put the site behind a coming soon page or a maintenance notice while you build
it, or password-protect it for a client to review — and control whether search
engines may index it.

= Security and performance =

Limit login attempts, disable XML-RPC, block user enumeration, turn off the
theme and plugin file editors, allow SVG uploads safely, and see when each user
last logged in. Plus auto-update controls, a post-revision limit and a
heartbeat limit.

= Everyday tools =

Header, body and footer scripts for analytics and verification tags. Duplicate
any post or page. Reorder content by dragging. Replace a media file without
breaking the links to it. Disable comments site-wide. Turn off blog features on
a site that is not a blog. Generate a child theme in a click.

= Two editor controls =

A transparent header that overlays your hero section, and per-breakpoint
responsive controls that extend the ones WordPress added in 7.1.

= Built on WordPress, not on top of it =

Every setting writes into WordPress's own options, post meta and block
attributes. Deactivate Blocklane and your content is still ordinary WordPress
content.

= Source code =

The JavaScript in this plugin is built with @wordpress/scripts. Source code and
build tools: https://github.com/BlocklaneWP/blocklane

== Installation ==

1. Install and activate the plugin from Plugins → Add New (search for "Blocklane"), or upload the zip.
2. Open Settings → Blocklane. Every tool is off until you turn it on; each one has its own screen and its own switch.
3. Turn on what you need. Nothing else loads.

= Support =

Questions and bug reports go to the plugin's support forum on WordPress.org. We support the current release on the last three major WordPress versions and PHP 8.1 or newer.

== Frequently Asked Questions ==

= Does this add its own blocks? =

The form and popup tools add blocks, because a form is content. The editor
controls do not — they add settings to the core blocks you already use.

= Will it conflict with my SEO plugin? =

No. If Yoast, Rank Math, AIOSEO or SEOPress is active, Blocklane's SEO output
stands down so your tags are never doubled.

= What happens if I deactivate it? =

Your content stays. Posts, pages, form submissions and settings are ordinary
WordPress records and are left exactly as they are.

= Is there a Pro version? =

Yes — Blocklane Pro adds content modeling, dynamic values, a mega menu, a
carousel, AI tools and a deeper set of editor controls. Your site keeps working
when a license lapses. You stop receiving updates.

= Does this plugin contact any external services? =

Not on its own. Out of the box Blocklane makes no outbound requests: nothing is
sent anywhere unless you turn on one of the two optional integrations below and
enter your own credentials for it. Both are off by default.

**Cloudflare Turnstile** (optional, for form spam protection)

Turn it on under Forms and paste your own Turnstile site key and secret key, and
forms will then load Cloudflare's widget script from
`https://challenges.cloudflare.com/turnstile/v0/api.js` on pages that contain a
form, and verify each submission against
`https://challenges.cloudflare.com/turnstile/v0/siteverify`.

What is sent on verification: the Turnstile token the widget produced, your
secret key, and the submitting visitor's IP address. No form field values and no
other personal data are sent. If you leave Turnstile off, neither URL is ever
requested.

Cloudflare terms: https://www.cloudflare.com/website-terms/
Cloudflare privacy policy: https://www.cloudflare.com/privacypolicy/

**IndexNow** (optional, for search engine notification)

Turn it on under SEO and generate a key, and publishing or updating content will
notify `https://api.indexnow.org` — the shared endpoint used by Bing, Yandex,
Seznam and Naver — so those engines learn a URL changed.

What is sent: your site's host name, the IndexNow key you generated, and the
URLs that changed. Nothing else, and never any post content or visitor data. If
you leave IndexNow off, the endpoint is never contacted.

IndexNow documentation and terms: https://www.indexnow.org/documentation

= What third-party code does it include? =

Two things, both bundled and both credited in full in credits.txt inside the
plugin folder: the SVG sanitizer (enshrined/svg-sanitize, GPL-2.0-or-later),
which cleans SVG uploads when you switch that option on, and the Unbounded and
Manrope typefaces (SIL Open Font License 1.1) used by the plugin's own
dashboard. Nothing else is bundled, and nothing is loaded from a third-party
CDN.

= Where does the plugin get its updates? =

From WordPress.org, like any other plugin here. Blocklane contains no updater of
its own and never contacts any other server for updates.

== Changelog ==

= 0.11.4 =
* First release on WordPress.org.
