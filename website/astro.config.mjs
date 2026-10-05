// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

export default defineConfig({
	site: 'https://getculpritfinder.com',
	trailingSlash: 'always',
	build: { format: 'directory' },
	integrations: [
		starlight({
			title: 'Culprit Finder',
			description: 'Find the plugin that broke your WordPress site. Plugins are switched off for your browser only, so visitors never notice.',
			logo: {
				light: './src/assets/brand/culprit-finder-logo.svg',
				dark: './src/assets/brand/culprit-finder-logo-white.svg',
				replacesTitle: true,
			},
			favicon: '/favicon.svg',
			head: [
				{ tag: 'link', attrs: { rel: 'icon', href: '/favicon.ico', sizes: 'any' } },
				{ tag: 'link', attrs: { rel: 'apple-touch-icon', href: '/apple-touch-icon.png' } },
				{ tag: 'meta', attrs: { property: 'og:image', content: 'https://getculpritfinder.com/og-image.png' } },
				{ tag: 'meta', attrs: { name: 'twitter:card', content: 'summary_large_image' } },
				{ tag: 'meta', attrs: { name: 'twitter:image', content: 'https://getculpritfinder.com/og-image.png' } },
			],
			customCss: ['./src/styles/starlight.css'],
			components: {
				Footer: './src/components/DocsFooter.astro',
			},
			credits: false,
			lastUpdated: false,
			pagination: true,
			sidebar: [
				{ label: 'Overview', link: '/docs/' },
				{
					label: 'Getting started',
					items: [
						{ label: 'Install', slug: 'docs/getting-started/install' },
						{ label: 'Your first run', slug: 'docs/getting-started/first-run' },
					],
				},
				{
					label: 'Using it',
					items: [
						{ label: 'Answering steps', slug: 'docs/using/answering-steps' },
						{ label: 'Keeping plugins on', slug: 'docs/using/keeping-plugins-on' },
						{ label: 'Your two safety links', slug: 'docs/using/safety-links' },
						{ label: 'When the whole site is down', slug: 'docs/using/whole-site-down' },
						{ label: 'Results and reports', slug: 'docs/using/results-and-reports' },
						{ label: 'Results history', slug: 'docs/using/results-history' },
					],
				},
				{
					label: 'Limits and help',
					items: [
						{ label: 'What it can’t test', slug: 'docs/help/what-it-cant-test' },
						{ label: 'Troubleshooting', slug: 'docs/help/troubleshooting' },
						{ label: 'Privacy and data', slug: 'docs/help/privacy-and-data' },
						{ label: 'Uninstalling', slug: 'docs/help/uninstalling' },
						{ label: 'FAQ', slug: 'docs/help/faq' },
					],
				},
				{
					label: 'For developers',
					items: [
						{ label: 'WP-CLI command', slug: 'docs/developers/wp-cli' },
						{ label: 'Hooks reference', slug: 'docs/developers/hooks' },
					],
				},
				{ label: 'Changelog', slug: 'docs/changelog' },
			],
		}),
	],
});
