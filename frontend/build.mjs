import * as esbuild from "esbuild";
import { rm } from "node:fs/promises";

// Бандл основного iframe: React, @moysklad/uikit и JS Widget SDK. PHP отдает только HTML-оболочку
// (src/php/entry/iframe.php), поэтому результат кладется в docroot рядом с ней.
const watch = process.argv.includes("--watch");
const outdir = "../src/php/assets/entry";

await rm(outdir, { recursive: true, force: true });

const context = await esbuild.context({
  entryPoints: { iframe: "src/iframe/main.tsx" },
  bundle: true,
  splitting: true,
  format: "esm",
  platform: "browser",
  target: ["es2022"],
  jsx: "automatic",
  outdir,
  loader: { ".woff2": "file" },
  assetNames: "[name]-[hash]",
  minify: process.env.NODE_ENV === "production",
  sourcemap: !watch && process.env.NODE_ENV === "production" ? false : "linked",
  define: { "process.env.NODE_ENV": JSON.stringify(process.env.NODE_ENV ?? "development") },
  logLevel: "info",
});

if (watch) {
  await context.watch();
} else {
  await context.rebuild();
  await context.dispose();
}
