const fs = require('node:fs/promises');
const path = require('node:path');
const { execFile } = require('node:child_process');
const { promisify } = require('node:util');

const execFileAsync = promisify(execFile);

async function syncReleaseVersion(cwd, version) {
	const versionFiles = [
		{ path: 'package.json', type: 'json' },
		{ path: 'block.json', type: 'json' },
		{ path: 'wipop.php', type: 'plugin-header' },
	];

	for (const file of versionFiles) {
		const absolutePath = path.join(cwd, file.path);

		if (file.type === 'json') {
			await updateJsonVersion(absolutePath, version);
			continue;
		}

		await updatePluginHeaderVersion(absolutePath, version);
	}
}

async function updateJsonVersion(filePath, version) {
	const raw = await fs.readFile(filePath, 'utf8');
	const parsed = JSON.parse(raw);

	parsed.version = version;

	await fs.writeFile(filePath, `${JSON.stringify(parsed, null, '\t')}\n`);
}

async function updatePluginHeaderVersion(filePath, version) {
	const raw = await fs.readFile(filePath, 'utf8');
	const next = raw.replace(/^(\s*\*\sVersion:\s).+$/m, `$1${version}`);

	if (next === raw) {
		throw new Error(`Unable to update plugin version header in ${filePath}`);
	}

	await fs.writeFile(filePath, next);
}

async function runCommand(cwd, command, args, logger) {
	const { stdout, stderr } = await execFileAsync(command, args, {
		cwd,
		maxBuffer: 1024 * 1024 * 20,
	});

	if (stdout.trim() !== '') {
		logger.log(stdout.trim());
	}

	if (stderr.trim() !== '') {
		logger.log(stderr.trim());
	}
}

async function buildReleaseZip(cwd, logger) {
	await fs.rm(path.join(cwd, 'wipop.zip'), { force: true });
	await runCommand(cwd, 'npm', ['run', 'build'], logger);
	await runCommand(
		cwd,
		'zip',
		[
			'-r',
			'wipop.zip',
			'.',
			'-x',
			'src/*',
			'-x',
			'node_modules/*',
			'-x',
			'tests/*',
			'-x',
			'scripts/*',
			'-x',
			'.*',
		],
		logger
	);
}

module.exports = {
	syncReleaseVersion,
	buildReleaseZip,
	prepare: async (_pluginConfig, context) => {
		const cwd = context.cwd || process.cwd();
		const version = context.nextRelease && context.nextRelease.version;

		if (!version) {
			throw new Error('hook did not receive nextRelease.version');
		}

		context.logger.log(`Syncing plugin metadata to release version ${version}`);
		await syncReleaseVersion(cwd, version);

		context.logger.log('Building release zip with synced metadata');
		await buildReleaseZip(cwd, context.logger);
	},
};
