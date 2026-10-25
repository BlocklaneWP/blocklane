=== Blocklane – Coming Soon, Popups, Forms, SEO & Security ===
Contributors: blocklane, garrettmichaelj
Tags: seo, contact form, popup, coming soon, security
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Everything a WordPress site should have out of the box: SEO, a contact form, popups, a coming soon page, and security hardening. In one plugin.

== Description ==

Blocklane gives a new WordPress site the things it needs on day one, without
installing six plugins to get them. Every tool is built on WordPress's own
blocks, settings and APIs — there is no page builder, no separate rendering
engine, and no proprietary markup.

Most tools start off. Five start on: Site Visibility, whose screen is ready
but locks nothing until you choose a mode; Responsive Settings, Transparent
Header and Menu Button, which add their settings to the editor and load one
small stylesheet on the front end; and the Carousel block. Switching a tool
off removes its blocks, its screen and its output, with one exception:
switching Site Visibility off hides its screen but leaves a lock that is
already on in place.

= SEO =

A search title, a meta description and a share image on every page and post,
with a live preview of the search result. The front end emits the matching
title tag, meta description, Open Graph tags and structured data, plus sitemap
controls. If a dedicated SEO plugin is active, Blocklane's output stands down
automatically so your tags are never doubled.

Google, Facebook and Pinterest are trademarks of their respective owners;
Blocklane is not affiliated with them.

= Contact forms =

Build a form out of blocks — text, email, textarea, select, radio, checkbox —
with a real email notification and submissions stored in your own database.
Spam protection is included. No shortcodes, no separate form builder.

= Popups =

A popup built like any other page content, shown on a delay, everywhere or on
the pages and posts you choose.

= Carousel =

A carousel built from blocks: slides that hold any blocks, or fill
themselves from a post query, with arrows, dots, bars, a scrollbar and a
play/pause button. Autoplay, fade, auto-height and per-device settings are
included. The Carousel is on from the start; switch it off in Blocklane →
Settings, and saved carousels come back the moment you switch it on again.

= Coming soon, maintenance mode and passwords =

Put the site behind a coming soon page or a maintenance notice while you build
it, or password-protect it for a client to review — and control whether search
engines may index it. A signup form on the coming soon page keeps accepting
submissions (and sends that form's emails) while the site is locked; the
maintenance notice accepts nothing.

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

== Source Code ==

Blocklane's PHP ships as source. Its JavaScript and CSS ship compiled, and the source of every compiled file is public:

* Repository: https://github.com/BlocklaneWP/blocklane
* This version: tag v1.0.0, https://github.com/BlocklaneWP/blocklane/tree/v1.0.0

Every build folder is compiled from the src folder beside it in the repository: inc/forms/src builds inc/forms/build, inc/onboarding/src builds inc/onboarding/build, and so on for each folder. The plugin's zip ships the build folders; the src folders are in the repository. A build file whose name is a number, such as 452.js, is a chunk webpack split out of that same src folder. The THIRD-PARTY-NOTICES.txt in each build folder names the npm packages in every file, with their versions and license texts.

To build it yourself with Node.js 22 and npm 10:

1. `git clone https://github.com/BlocklaneWP/blocklane.git`
2. `cd blocklane`
3. `git checkout v1.0.0`
4. `npm ci`
5. `npm run build`

The build uses @wordpress/scripts (webpack) and writes the same build folders this plugin ships. We build the tagged source and check that every compiled file matches this plugin's files byte for byte.

Bundled npm packages, by name and version. Each one's source is linked from its page on npm (`https://www.npmjs.com/package/<name>`):

* @ariakit/components 0.1.13
* @ariakit/react-components 0.6.1
* @ariakit/react-store 0.1.11
* @ariakit/react-utils 0.2.6
* @ariakit/store 0.1.10
* @ariakit/utils 0.2.1
* @base-ui/react 1.8.0
* @base-ui/utils 0.4.0
* @date-fns/tz 1.5.0
* @floating-ui/core 1.8.0
* @floating-ui/dom 1.8.0
* @floating-ui/react-dom 2.1.9
* @floating-ui/utils 0.2.12
* @wordpress/admin-ui 2.11.0
* @wordpress/dataviews 19.1.0
* @wordpress/icons 17.0.0
* @wordpress/kebab-case 1.3.0
* @wordpress/ui 0.23.0
* clsx 2.1.1
* colord 2.10.0
* date-fns 4.4.0
* dot-case 3.0.4
* fast-deep-equal 3.1.3
* lower-case 2.0.2
* no-case 3.0.4
* param-case 3.0.4
* react-day-picker 10.0.1
* remove-accents 0.5.0
* tslib 2.8.1
* use-sync-external-store 1.6.0

== Installation ==

1. Install and activate the plugin from Plugins → Add New (search for "Blocklane"), or upload the zip.
2. Open Blocklane → Settings. Every tool has its own switch there, and the ones with more to set up have their own screen. Most start off; Site Visibility, Responsive Settings, Transparent Header, Menu Button and the Carousel block start on.
3. Turn on what you need, and off what you do not.

= Support =

Questions and bug reports go to the plugin's support forum on WordPress.org. We support the current release on WordPress 7.1 or newer and PHP 8.1 or newer, the versions this readme's header requires.

== Frequently Asked Questions ==

= My site is behind a CDN or a proxy. Do the visitor limits still work? =

Yes. Three limits count attempts per visitor address: contact form submissions, Coming soon password attempts and login attempts (an IPv6 address counts per /64 network). Behind a CDN or reverse proxy every request can arrive from the proxy's address, so return the real visitor address from the `blocklane_pro_client_ip` filter, read from the header your proxy sets. One filter covers all three limits; its second argument names which one is asking (`forms`, `site-lock` or `login`). The older `blocklane_pro_forms_client_ip` and `blocklane_pro_login_client_ip` filters still work and are deprecated.

= Does this add its own blocks? =

The form, popup and carousel tools add blocks, because a form or a carousel
is content. The editor controls do not — they add settings to the core blocks
you already use.

= Does it include block patterns? =

Yes, a few: headers you can pick from a header's Design panel, and layouts
built around the contact form and popups. They use WordPress's own blocks
and Blocklane's free features, and they work with any block theme. A
pattern that needs a feature you have switched off, such as the contact
form, stays hidden until you switch it on.

The patterns use your theme's color, font and spacing settings, so they look
their best with the free Blocklane theme, which is designed alongside them.
On another block theme some colors and spacing can differ; change them in
the editor like any other block.

The transparent header pattern floats over a page that opens with a cover
banner and turns your logo white there. If your banner is light, switch the
logo to Black, or choose a separate transparent logo, in the logo block's
Transparent Header settings.

= Will it conflict with my SEO plugin? =

No. If Yoast, Rank Math, AIOSEO or SEOPress is active, Blocklane's SEO output
stands down so your tags are never doubled.

= What happens if I deactivate it? =

Your content stays. Posts, pages, form submissions and settings are ordinary
WordPress records and are left exactly as they are.

= Is there a Pro version? =

Yes — Blocklane Pro adds content modeling, dynamic values, a mega menu, AI
tools, header, body and footer code, and a deeper set of editor controls — and, inside features this plugin already has, multi-step forms, file
upload fields, scroll and exit-intent popups with front-page, post-type, URL
and logged-in targeting, and forced auto-updates for plugins and themes. A form
or popup built with those parts keeps every setting here and works again in
full the moment Pro is active. Your site keeps working when a license lapses.
You stop receiving updates.

The Pro extensions and Pro tools that belong on the Settings screen are listed
there, marked "Pro", each with one sentence saying what it does and a switch
drawn in the off position that you cannot press, because the feature is not in
this plugin. Opening the row shows a card that describes the feature, with one
link to its page on blocklanewp.com, so you can see what each one does and
decide whether you want it — nothing there is a part of this plugin that has
been switched off. The rest of Pro (content
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
* the Phosphor Icons starter set (MIT), the 75 SVGs the Icon block offers
  under "Blocklane", and the Phosphor arrow glyphs and block icons the
  Carousel uses (MIT);
* the npm packages the build bundles into the plugin's scripts and styles,
  each listed by name and version in the Source Code section above. The
  build writes a THIRD-PARTY-NOTICES.txt into every build folder, naming each
  bundle, the packages in it, and each package's version and license text.

The plugin loads its own scripts, styles and fonts from its own folder; the
one file it can load from another site is Cloudflare's Turnstile widget
script, and only on pages with a form after you turn Turnstile on (see the
external services answer above).

= Where does the plugin get its updates? =

From WordPress.org, like any other plugin here. Blocklane contains no updater of
its own and never contacts any other server for updates.

== Changelog ==

= 1.0.0 =
* Initial release.
