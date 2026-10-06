=== Blocklane ===
Contributors: blocklane, garrettmichaelj
Tags: seo, contact form, popup, coming soon, security
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Everything a WordPress site should have out of the box: SEO, a contact form, popups, a coming soon page, and security hardening. In one plugin.

== Description ==

Blocklane gives a new WordPress site the things it needs on day one, without
installing six plugins to get them. Every tool is built on WordPress's own
blocks, settings and APIs — there is no page builder, no separate rendering
engine, and no proprietary markup.

Each tool is off until you turn it on. What you do not use costs nothing: a
tool that is off registers no blocks, no admin screens, no routes and no output.

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
last logged in. Plus a post-revision limit and a heartbeat limit.

= Everyday tools =

Duplicate any post or page. Reorder content by dragging. Replace a media file
without breaking the links to it. Disable comments site-wide. Turn off blog
features on a site that is not a blog. Generate a child theme in a click.

= Two editor controls =

A transparent header that overlays your hero section, and per-breakpoint
responsive controls that extend the ones WordPress added in 7.1.

= Built on WordPress, not on top of it =

Every setting writes into WordPress's own options, post meta and block
attributes. Deactivate Blocklane and your content is still ordinary WordPress
content.

== Installation ==

1. Install and activate the plugin from Plugins → Add New (search for "Blocklane"), or upload the zip.
2. Open Settings → Blocklane. Every tool is off until you turn it on; each one has its own screen and its own switch.
3. Turn on what you need. Nothing else loads.

= Support =

Questions and bug reports go to the plugin's support forum on WordPress.org. We support the current release on WordPress 7.1 or newer and PHP 8.1 or newer, the versions this readme's header requires.

== Frequently Asked Questions ==

= Where is the source code for the compiled JavaScript and CSS? =

Every script and stylesheet in the plugin's build folders is compiled from source we publish in full at https://github.com/BlocklaneWP/blocklane, with one tagged commit per release. Each bundle's source is the src folder beside its build folder (for example, inc/forms/src builds inc/forms/build).

To build it yourself with Node.js 22 and npm 10:

1. `git clone https://github.com/BlocklaneWP/blocklane.git`
2. `cd blocklane`
3. `git checkout v1.0.1` (this version's tag; every release has one, named v and its version number)
4. `npm ci`
5. `npm run build`

The build uses @wordpress/scripts (webpack) and writes the same build folders this plugin ships, each with a THIRD-PARTY-NOTICES.txt naming the packages it bundles.

= My site is behind a CDN or a proxy. Does the form rate limit still work? =

Yes. Contact forms limit submissions per visitor address (an IPv6 address counts per /64 network). Behind a CDN or reverse proxy every request can arrive from the proxy's address, so return the real visitor address from the `blocklane_pro_forms_client_ip` filter, read from the header your proxy sets.

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
carousel, AI tools, header, body and footer code, and a deeper set of editor
controls — and, inside features this plugin already has, multi-step forms, file
upload fields, scroll and exit-intent popups with front-page, post-type, URL
and logged-in targeting, and forced auto-updates for plugins and themes. A form
or popup built with those parts keeps every setting here and works again in
full the moment Pro is active. Your site keeps working when a license lapses.
You stop receiving updates.

The Pro extensions and Pro tools that belong on the Extensions and Advanced
screens are listed there, marked "Pro", with a description and no switch, so
you can see what each one does and decide whether you want it — nothing there
is a part of this plugin that has been switched off. The rest of Pro (content
modeling, the mega menu, form steps and file upload fields, popup triggers and
targeting) has no row here and is described on the Pro site. The code for a Pro
feature is not included in this download at all, so there is nothing a payment
would unlock here; Pro is a separate plugin you would install alongside or
instead of this one. If you never install it, the rows are the only trace of
it.

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

All of it is credited in credits.txt inside the plugin folder:

* the SVG sanitizer (enshrined/svg-sanitize, GPL-2.0-or-later), which cleans
  SVG uploads when you switch that option on;
* the Unbounded and Manrope typefaces (SIL Open Font License 1.1) used by the
  plugin's own dashboard;
* the Phosphor Icons starter set (MIT), the 75 SVGs the Icon block offers
  under "Blocklane";
* npm packages the build bundles into the plugin's scripts and styles:
  WordPress packages WordPress itself does not load, such as
  @wordpress/dataviews and @wordpress/icons (GPL-2.0-or-later), and the MIT
  and 0BSD packages they use, such as Ariakit, Base UI, Floating UI, date-fns
  and clsx. The build writes a THIRD-PARTY-NOTICES.txt into every build
  folder, naming each bundle, the packages in it, and each package's version
  and license text.

The plugin loads its own scripts, styles and fonts from its own folder; the
one file it can load from another site is Cloudflare's Turnstile widget
script, and only on pages with a form after you turn Turnstile on (see the
external services answer above).

= Where does the plugin get its updates? =

From WordPress.org, like any other plugin here. Blocklane contains no updater of
its own and never contacts any other server for updates.

== Changelog ==

= 1.0.1 =
* Every inline style and script the plugin adds (on its admin screens, the Coming Soon and Maintenance page, popups, the HTML sitemap page, the fallback that reveals scroll-animated blocks, and the structured data) is now printed through WordPress's own style and script functions.
* Form fields escape every attribute where it is printed, and forms, groups and notifications return their inner blocks the way core's container blocks do. Forms look and work exactly as before.
* The form submission route stores nothing for a form ID no page carries, and answers a form ID longer than any form has without looking it up. The per-visitor limits on form submissions and on the Coming Soon password count an IPv6 address per /64 network.
* Reordering content checks that you can edit every item whose position it changes. When it cannot, the message names the item that blocks the move and says who can make it.
* The "needs a block theme" and "site lock is not enforced" notices appear only on the Dashboard, Plugins and Themes screens, and only to users who can act on them.
* The FAQ says where the source code is and how to build it.

= 1.0.0 =
* First release on WordPress.org.
* Requires WordPress 7.1 or later.
* Works beside Blocklane Pro. Content built with Pro is kept exactly as saved here: a stepped form shows as one page, a mega menu item renders as a plain dropdown, a popup keeps every setting and opens from links and buttons. It all returns to full the moment Pro is active.
* The Extensions and Advanced screens list the features that are part of Blocklane Pro, marked "Pro", so you can read what each one does. They have no switch, because that code is not in this download.
* Forced auto-updates for plugins and themes are part of Blocklane Pro. The two rows are listed on the Advanced screen's Updates & Performance tab, marked "Pro", and a value Pro stored is kept as it is.
* Header, body and footer code is part of Blocklane Pro, because WordPress.org does not accept plugins that insert arbitrary code. Its row is listed on the Advanced screen's Site Tools tab, marked "Pro". Code saved with Pro stays in your database, and Pro prints it again the moment it is active.
* The Icon block offers the Blocklane collection: 75 bundled icons, plus any your site saved earlier with Blocklane Pro. Icons already placed in your pages keep rendering.
