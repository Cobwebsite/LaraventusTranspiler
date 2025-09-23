import { existsSync } from 'fs'
import { copyFile, mkdir, readdir, rm, symlink } from 'fs/promises'
import { dirname, join, normalize, relative } from 'path'
import { exec } from 'child_process'


const rootDir = normalize(process.cwd() + '/..');
const srcDir = join(rootDir, 'src');
const targetDir = join(rootDir, 'transpiler', 'vendor', 'aventus', 'laraventus');
const transpilerDir = join(rootDir, 'transpiler');
const outputBin = join(transpilerDir, "PhptoTypescript.phar")
const extensionOutput = "D:\\Aventus\\Aventus\\lib\\bin\\PhpToTypescript\\PhptoTypescript.phar"

async function deleteFolder(folderPath) {
    if (existsSync(folderPath)) {
        await rm(folderPath, { recursive: true, force: true });
        console.log(`Supprimé : ${folderPath}`);
    }
}

async function copyFolder(src, dest) {
    await mkdir(dest, { recursive: true });
    const entries = await readdir(src, { withFileTypes: true });

    for (const entry of entries) {
        const srcPath = join(src, entry.name);
        const destPath = join(dest, entry.name);

        if (entry.name === 'vendor') continue; // Skip vendor inside src

        if (entry.isDirectory()) {
            await copyFolder(srcPath, destPath);
        } else {
            await copyFile(srcPath, destPath);
        }
    }
}

function runCommand(command, cwd) {
    return new Promise((resolve, reject) => {
        const proc = exec(command, { cwd });

        proc.stdout.on('data', (data) => {
            process.stdout.write(data);
        });

        proc.stderr.on('data', (data) => {
            process.stderr.write(data);
        });

        proc.on('close', (code) => {
            if (code === 0) {
                resolve();
            } else {
                reject(new Error(`Commande échouée avec code ${code}`));
            }
        });
    });
}

async function createSymlink(target, linkPath) {
    try {
        await symlink(relative(dirname(linkPath), target), linkPath, 'junction');
        console.log(`Symlink créé : ${linkPath} -> ${target}`);
    } catch (err) {
        console.error('Erreur création symlink :', err.message);
    }
}

async function copyBin(src, dest) {
    await copyFile(src, dest);
}

async function main() {
    try {
        await deleteFolder(targetDir);

        await copyFolder(srcDir, targetDir);
        console.log('Contenu copié de src vers laraventus');

        try {
            await runCommand('box compile', transpilerDir);
        } catch {
            await runCommand('box compile --composer-bin C:\\ProgramData\\ComposerSetup\\bin\\composer.bat', transpilerDir);
        }
        console.log('"box compile" exécuté avec succès');

        await deleteFolder(targetDir);

        await createSymlink(srcDir, targetDir);

        await copyBin(outputBin, extensionOutput);
        console.log('move inside extension');
    } catch (err) {
        console.error('Erreur dans le script :', err);
    }
}

main();