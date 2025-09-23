<?php

namespace Aventus\Transpiler\Tools;

class Path
{

    public static function getRelativePath(string $currentPathTxt, string $importPathTxt): string
    {
        $currentPath = explode(DIRECTORY_SEPARATOR, $currentPathTxt);
        // Supprime le dernier élément (le fichier), pour partir du dossier
        array_pop($currentPath);

        $importPath = explode(DIRECTORY_SEPARATOR, $importPathTxt);

        $i = 0;
        while ($i < count($currentPath) && $i < count($importPath)) {
            if ($currentPath[$i] === $importPath[$i]) {
                // Supprime des deux chemins le dossier commun
                array_splice($currentPath, $i, 1);
                array_splice($importPath, $i, 1);
            } else {
                break;
            }
        }

        $finalPathToImport = str_repeat('../', count($currentPath));
        if ($finalPathToImport === '') {
            $finalPathToImport = './';
        }
        $finalPathToImport .= implode('/', $importPath);

        return $finalPathToImport;
    }
}
