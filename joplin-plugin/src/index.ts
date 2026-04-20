import joplin from 'api';
import { SettingItemType } from 'api/types';

const SETTINGS_SECTION = 'shareToWechat';
const SETTING_SOURCE_BASE_URL = 'sourceBaseUrl';
const SETTING_SHARE_BASE_URL = 'shareBaseUrl';
const CLIPBOARD_POLL_INTERVAL_MS = 500;

class ShareUrlTransformer {
    private sourceBaseUrl: string;
    private shareBaseUrl: string;
    private sharePattern: RegExp;

    constructor(sourceBaseUrl: string, shareBaseUrl: string) {
        this.sourceBaseUrl = sourceBaseUrl.replace(/\/$/, '');
        this.shareBaseUrl = shareBaseUrl.replace(/\/$/, '');
        this.sharePattern = new RegExp(
            `^${this.escapeRegex(this.sourceBaseUrl)}/shares/([a-zA-Z0-9_/-]+)$`
        );
    }

    private escapeRegex(str: string): string {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    transform(url: string): string | null {
        const match = url.match(this.sharePattern);
        if (!match) return null;
        return `${this.shareBaseUrl}/${match[1]}`;
    }

    isJoplinShareUrl(url: string): boolean {
        return this.sharePattern.test(url);
    }
}

let transformer: ShareUrlTransformer | null = null;
let lastClipboard = '';

joplin.plugins.register({
    onStart: async function() {
        console.info('Share to WeChat plugin started');

        // Register settings section
        await joplin.settings.registerSection(SETTINGS_SECTION, {
            label: 'Share to WeChat',
        });

        // Register settings
        await joplin.settings.registerSettings({
            [SETTING_SOURCE_BASE_URL]: {
                value: 'https://home.flaresky.top:8443',
                type: SettingItemType.String,
                section: SETTINGS_SECTION,
                public: true,
                label: 'Joplin Server Base URL',
                description: 'The base URL of your Joplin Server',
            },
            [SETTING_SHARE_BASE_URL]: {
                value: 'http://stock.flaresky.top:40080/fs/',
                type: SettingItemType.String,
                section: SETTINGS_SECTION,
                public: true,
                label: 'Share Redirect Base URL',
                description: 'The base URL for beautified share links',
            },
        });

        // Build initial transformer
        const sourceBaseUrl = await joplin.settings.value(SETTING_SOURCE_BASE_URL);
        const shareBaseUrl = await joplin.settings.value(SETTING_SHARE_BASE_URL);
        transformer = new ShareUrlTransformer(sourceBaseUrl, shareBaseUrl);

        // Rebuild transformer when settings change
        await joplin.settings.onChange(async () => {
            const src = await joplin.settings.value(SETTING_SOURCE_BASE_URL);
            const dst = await joplin.settings.value(SETTING_SHARE_BASE_URL);
            transformer = new ShareUrlTransformer(src, dst);
            console.info('Settings changed, transformer rebuilt');
        });

        // Start clipboard watcher
        setInterval(async () => {
            try {
                const text = await joplin.clipboard.readText();
                if (!text || text === lastClipboard) return;

                if (transformer && transformer.isJoplinShareUrl(text)) {
                    const transformed = transformer.transform(text);
                    if (transformed && transformed !== text) {
                        await joplin.clipboard.writeText(transformed);
                        lastClipboard = transformed;
                        console.info('URL transformed:', transformed);
                    }
                } else {
                    lastClipboard = text;
                }
            } catch (_err) {
                // Clipboard may be unavailable - silently ignore
            }
        }, CLIPBOARD_POLL_INTERVAL_MS);
    },
});
