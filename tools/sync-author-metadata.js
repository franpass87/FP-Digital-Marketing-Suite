#!/usr/bin/env node
/* eslint-disable no-console */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const APPLY = process.argv.some((arg) => arg.startsWith('--apply') && !arg.endsWith('false'));
const UPDATE_DOCS = process.argv.includes('--docs');

const AUTHOR = {
  name: 'Francesco Passeri',
  email: 'info@francescopasseri.com',
  uri: 'https://francescopasseri.com'
};

const DESCRIPTION = 'Automates marketing performance reporting, anomaly detection, and multi-channel alerts for private WordPress operations.';

const targets = [];

function queueUpdate(filePath, updater) {
  targets.push({ filePath, updater });
}

function ensureDir(filePath) {
  const dir = path.dirname(filePath);
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

function writeFile(filePath, content) {
  ensureDir(filePath);
  fs.writeFileSync(filePath, content, 'utf8');
}

function updateJson(filePath, mutate) {
  queueUpdate(filePath, () => {
    if (!fs.existsSync(filePath)) {
      return { skipped: true, reason: 'missing' };
    }

    const original = fs.readFileSync(filePath, 'utf8');
    let data;
    try {
      data = JSON.parse(original);
    } catch (error) {
      return { skipped: true, reason: 'invalid json' };
    }

    const mutated = mutate({ ...data });
    const output = JSON.stringify(mutated, null, 2) + '\n';
    if (output === original) {
      return { changed: false };
    }

    if (!APPLY) {
      writeFile(`${filePath}.bak`, original);
      return { changed: true, preview: diffPreview(original, output) };
    }

    writeFile(filePath, output);
    return { changed: true };
  });
}

function updateText(filePath, mutate) {
  queueUpdate(filePath, () => {
    if (!fs.existsSync(filePath)) {
      return { skipped: true, reason: 'missing' };
    }

    const original = fs.readFileSync(filePath, 'utf8');
    const output = mutate(original);
    if (output === original) {
      return { changed: false };
    }

    if (!APPLY) {
      writeFile(`${filePath}.bak`, original);
      return { changed: true, preview: diffPreview(original, output) };
    }

    writeFile(filePath, output);
    return { changed: true };
  });
}

function diffPreview(before, after) {
  const beforeLines = before.split('\n');
  const afterLines = after.split('\n');
  const preview = [];
  const max = Math.max(beforeLines.length, afterLines.length);
  for (let i = 0; i < max; i += 1) {
    if (beforeLines[i] !== afterLines[i]) {
      preview.push(`- ${beforeLines[i] ?? ''}`);
      preview.push(`+ ${afterLines[i] ?? ''}`);
    }
  }
  return preview.join('\n');
}

updateText(path.join(ROOT, 'fp-digital-marketing-suite.php'), (content) => {
  return content
    .replace(/\* Plugin URI:.*\n/, `* Plugin URI: ${AUTHOR.uri}\n`)
    .replace(/\* Description:.*\n/, `* Description: ${DESCRIPTION}\n`)
    .replace(/\* Author:.*\n/, `* Author: ${AUTHOR.name}\n`)
    .replace(/\* Author URI:.*\n/, `* Author URI: ${AUTHOR.uri}\n`);
});

updateText(path.join(ROOT, 'readme.txt'), (content) => {
  let updated = content;
  updated = updated.replace(/Contributors:.*\n/, (line) => {
    const contributors = new Set(
      line
        .replace('Contributors:', '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
    );
    contributors.add('francescopasseri');
    return `Contributors: ${Array.from(contributors).join(', ')}\n`;
  });
  updated = updated.replace(/Stable tag:.*\n/, 'Stable tag: 0.1.1\n');
  updated = updated.replace(/Requires at least:.*\n/, 'Requires at least: 6.4\n');
  updated = updated.replace(/Tested up to:.*\n/, 'Tested up to: 6.4\n');
  updated = updated.replace(/Requires PHP:.*\n/, 'Requires PHP: 8.1\n');
  updated = updated.replace(/\n[^\n]*?\n== Description ==/, `\n${DESCRIPTION}\n\n== Description ==`);
  return updated;
});

updateJson(path.join(ROOT, 'composer.json'), (data) => {
  const clone = { ...data };
  clone.description = DESCRIPTION;
  clone.homepage = AUTHOR.uri;
  clone.authors = [
    {
      name: AUTHOR.name,
      email: AUTHOR.email,
      homepage: AUTHOR.uri,
      role: 'Developer'
    }
  ];
  clone.support = clone.support || {};
  clone.support.issues = 'https://github.com/francescopasseri/FP-Digital-Marketing-Suite/issues';
  clone.support.source = 'https://github.com/francescopasseri/FP-Digital-Marketing-Suite';
  clone.require = clone.require || {};
  clone.require.php = '>=8.1';
  clone.scripts = clone.scripts || {};
  clone.scripts['sync:author'] = 'node tools/sync-author-metadata.js --apply=true';
  clone.scripts['sync:docs'] = 'node tools/sync-author-metadata.js --docs --apply=true';
  clone.scripts['changelog:from-git'] = 'conventional-changelog -p angular -i CHANGELOG.md -s || true';
  return clone;
});

updateJson(path.join(ROOT, 'package.json'), (data) => {
  const clone = { ...data };
  clone.name = 'fp-digital-marketing-suite';
  clone.version = clone.version || '0.1.1';
  clone.private = true;
  clone.description = DESCRIPTION;
  clone.author = `${AUTHOR.name} <${AUTHOR.email}> (${AUTHOR.uri})`;
  clone.homepage = AUTHOR.uri;
  clone.bugs = { url: 'https://github.com/francescopasseri/FP-Digital-Marketing-Suite/issues' };
  clone.license = 'GPL-2.0-or-later';
  clone.scripts = clone.scripts || {};
  clone.scripts['sync:author'] = 'node tools/sync-author-metadata.js --apply=true';
  clone.scripts['sync:docs'] = 'node tools/sync-author-metadata.js --docs --apply=true';
  clone.scripts['changelog:from-git'] = 'conventional-changelog -p angular -i CHANGELOG.md -s || true';
  return clone;
});

if (UPDATE_DOCS) {
  updateText(path.join(ROOT, 'README.md'), (content) => content.replace(/Automates marketing performance reporting[\s\S]*?operations\./, DESCRIPTION));
}

const results = [];
let exitCode = 0;
for (const target of targets) {
  const { filePath, updater } = target;
  const result = updater();
  if (result && result.changed) {
    results.push({ filePath: path.relative(ROOT, filePath), status: APPLY ? 'updated' : 'preview', preview: result.preview });
  } else if (result && result.skipped) {
    results.push({ filePath: path.relative(ROOT, filePath), status: `skipped (${result.reason})` });
  }
  if (result && result.preview) {
    console.log(`\nPreview for ${filePath}:\n${result.preview}`);
  }
}

if (!results.length) {
  console.log('No changes required.');
} else {
  console.table(results.map(({ filePath, status }) => ({ file: filePath, status })));
}

process.exit(exitCode);

