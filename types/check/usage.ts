// Checks the declarations the way editor and view scripts use them; type-checked by `npm run lint:types`.
import {
	getData,
	loadScript,
	registerControl,
	registerPanel,
	registerSupportControl,
	registerToolbarButton,
	type ControlProps,
	type PanelProps,
} from '@saij/wp-app-framework';
import { BlockView, DocumentView, SupportView } from '@saij/wp-app-framework/view';

registerControl<string>('hero', 'title', ({ value, onChange, clientId }: ControlProps<string>) => {
	onChange(value + clientId);
	return null;
});
registerSupportControl('surface', ({ value, attributes }) => [value, attributes]);
registerPanel('hero', ({ attributes, setAttributes, isPreview }: PanelProps) => {
	setAttributes({ shown: !isPreview && attributes.shown !== true });
	return null;
});
registerToolbarButton('hero', ({ clientId }) => clientId);
void loadScript('https://cdn.example.test/lib.js', { async: true, integrity: 'sha384-…' });
const key: unknown = getData('key');

export class Faq extends BlockView {
	mount(): void {
		const button = this.find('button');
		if (button !== null) {
			this.listen(button, 'click', () => this.findAll('p'));
		}
		this.onUnmount(() => void this.data('answers'));
	}
}

export class Surface extends SupportView {
	mount(): void {
		this.element.setAttribute('data-surface', String(this.value));
	}
}

export class Analytics extends DocumentView {
	mount(): void {
		void this.loadScript('//cdn.example.test/analytics.js').then(() => this.window.scrollY + this.document.title.length + Number(this.isPreview));
	}
}

export { key };
