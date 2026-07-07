import { defineConfig } from "vite";
import tailwindcss from "@tailwindcss/vite";
import laravel, { refreshPaths } from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        tailwindcss(),

        laravel({
            input: ["resources/css/app.css", "resources/js/app.js", "resources/css/filament/team/theme.css"],
            refresh: [...refreshPaths, "app/Livewire/**"],
        }),
    ],
});
