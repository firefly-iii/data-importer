/*
 * vite.config.js
 * Copyright (c) 2024 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import manifestSRI from 'vite-plugin-manifest-sri';
import * as fs from "fs";

const host = '127.0.0.1';

function manualChunks(id) {
    if (id.includes('node_modules')) {
        return 'vendor';
    }
}

export default defineConfig({
    base: './',
    build: {
        rollupOptions: {
            output: {
                manualChunks,
            },
        }
    },
    plugins: [
        laravel({
            input: [
                // css
                'src/sass/app.scss',

                // index
                'src/pages/configuration/index.js',
                'src/pages/conversion/index.js',
                'src/pages/index/index.js',
                'src/pages/selection/gocardless.js',
                'src/pages/submit/index.js',
            ],
            publicDirectory: '../../../public',
            refresh: true
        }),
        manifestSRI(),

    ],


    server: {
        watch: {
            usePolling: true,
        },
        https: {
            //key: fs.readFileSync(`/sites/vm/tls-certificates/wildcard.sd.internal.key`),
            //cert: fs.readFileSync(`/sites/vm/tls-certificates/wildcard.sd.internal.crt`),
        },

        host: 'firefly-data.sd.internal',
    },
});
