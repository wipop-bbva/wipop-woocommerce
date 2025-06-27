/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { Dropdown } from '@wordpress/components';
import * as Woo from '@woocommerce/components';
import { Fragment } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './index.scss';

const MyExamplePage = () => (
	<Fragment>
		<Woo.Section component="article">
			<Woo.SectionHeader title={__('Search', 'wipop')} />
			<Woo.Search
				type="products"
				placeholder="Search for something"
				selected={[]}
				onChange={(items) => setInlineSelect(items)}
				inlineTags
			/>
		</Woo.Section>

		<Woo.Section component="article">
			<Woo.SectionHeader title={__('Dropdown', 'wipop')} />
			<Dropdown
				renderToggle={({ isOpen, onToggle }) => (
					<Woo.DropdownButton
						onClick={onToggle}
						isOpen={isOpen}
						labels={['Dropdown']}
					/>
				)}
				renderContent={() => <p>Dropdown content here</p>}
			/>
		</Woo.Section>

		<Woo.Section component="article">
			<Woo.SectionHeader title={__('Pill shaped container', 'wipop')} />
			<Woo.Pill className={'pill'}>
				{__('Pill Shape Container', 'wipop')}
			</Woo.Pill>
		</Woo.Section>

		<Woo.Section component="article">
			<Woo.SectionHeader title={__('Spinner', 'wipop')} />
			<Woo.H>I am a spinner!</Woo.H>
			<Woo.Spinner />
		</Woo.Section>

		<Woo.Section component="article">
			<Woo.SectionHeader title={__('Datepicker', 'wipop')} />
			<Woo.DatePicker
				text={__('I am a datepicker!', 'wipop')}
				dateFormat={'MM/DD/YYYY'}
			/>
		</Woo.Section>
	</Fragment>
);

addFilter('woocommerce_admin_pages_list', 'wipop', (pages) => {
	pages.push({
		container: MyExamplePage,
		path: '/wipop',
		breadcrumbs: [__('Wipop', 'wipop')],
		navArgs: {
			id: 'wipop',
		},
	});

	return pages;
});
