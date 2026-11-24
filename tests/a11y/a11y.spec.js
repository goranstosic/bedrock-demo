// @ts-check
const { test, expect } = require( '@playwright/test' );
const { default: AxeBuilder } = require( '@axe-core/playwright' );

/*
 * Every page template and every state of the custom blocks, including the
 * empty state of Client Story Cards (Public Sector outside Australia).
 */
const PAGES = [
	{ name: 'home', path: '/' },
	{ name: 'home, UK', path: '/?region=uk' },
	{ name: 'sector with stories', path: '/sectors/financial-services/' },
	{ name: 'sector with stories, US', path: '/sectors/financial-services/?region=us' },
	{ name: 'sector without stories', path: '/sectors/public-sector/' },
	{ name: 'sector archive', path: '/sectors/' },
	{ name: 'client story archive, UK', path: '/client-stories/?region=uk' },
	{ name: 'client story', path: '/client-stories/harbour-bank-onboarding/' },
	{ name: 'not found', path: '/this-page-does-not-exist/', status: 404 },
];

const WCAG_TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ];

for ( const page of PAGES ) {
	test( `${ page.name } has no detectable WCAG 2.2 AA violations`, async ( { page: browserPage } ) => {
		const response = await browserPage.goto( page.path );
		expect( response?.status() ).toBe( page.status ?? 200 );

		const results = await new AxeBuilder( { page: browserPage } )
			.withTags( WCAG_TAGS )
			.analyze();

		expect(
			results.violations.map( ( v ) => `${ v.id }: ${ v.help } (${ v.nodes.length } nodes)` )
		).toEqual( [] );
	} );
}

test( 'skip link moves focus to the main content', async ( { page } ) => {
	await page.goto( '/' );
	await page.keyboard.press( 'Tab' );

	const skipLink = page.locator( '.skip-link' );
	await expect( skipLink ).toBeFocused();
	await skipLink.press( 'Enter' );
	await expect( page ).toHaveURL( /#wp--skip-link--target$/ );
} );

test( 'each client story card has exactly one link, reachable by keyboard', async ( { page } ) => {
	await page.goto( '/sectors/financial-services/?region=uk' );

	const cards = page.locator( '.nw-story-card' );
	await expect( cards ).toHaveCount( 2 );

	for ( const card of await cards.all() ) {
		await expect( card.getByRole( 'link' ) ).toHaveCount( 1 );
	}

	const firstLink = cards.first().getByRole( 'link' );
	await firstLink.focus();
	await expect( firstLink ).toBeFocused();
	await expect( firstLink ).toHaveAttribute( 'href', /\?region=uk$/ );
} );

test( 'empty Client Story Cards block renders nothing', async ( { page } ) => {
	await page.goto( '/sectors/public-sector/' );
	await expect( page.locator( '.nw-story-cards' ) ).toHaveCount( 0 );
	await expect( page.locator( '.nw-editor-hint' ) ).toHaveCount( 0 );

	await page.goto( '/sectors/public-sector/?region=au' );
	await expect( page.locator( '.nw-story-card' ) ).toHaveCount( 1 );
} );

test( 'region switcher marks the current region and keeps the page', async ( { page } ) => {
	await page.goto( '/sectors/healthcare/?region=au' );

	const switcher = page.getByRole( 'navigation', { name: 'Region' } );
	await expect( switcher.getByRole( 'link', { name: 'Australia' } ) ).toHaveAttribute( 'aria-current', 'true' );
	await expect( switcher.locator( '[aria-current]' ) ).toHaveCount( 1 );

	await switcher.getByRole( 'link', { name: 'United Kingdom' } ).click();
	await expect( page ).toHaveURL( /\/sectors\/healthcare\/\?region=uk$/ );
} );
