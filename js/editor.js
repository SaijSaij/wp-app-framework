/**
 * Entry of the editor runtime. Built by the consumer's build (webpack.config.js
 * of the framework), which defines WP_APP_FRAMEWORK_SLUG.
 */
/* global WP_APP_FRAMEWORK_SLUG */
import { startRuntime } from './runtime';

import './editor.scss';

startRuntime( WP_APP_FRAMEWORK_SLUG );
