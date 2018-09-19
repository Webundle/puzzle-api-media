<?php

namespace Puzzle\Api\MediaBundle;

/**
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 *
 */
final class PuzzleApiMediaEvents
{
    const MEDIA_CREATE_FILE = "papis.media.create_file";
    const MEDIA_COPY_FILE = "papis.media.copy_file";
    const MEDIA_RENAME_FILE = "papis.media.rename_file";
    const MEDIA_REMOVE_FILE = "papis.media.remove_file";
    
    const MEDIA_CREATE_FOLDER = "papis.media.create_folder";
    const MEDIA_RENAME_FOLDER = "papis.media.rename_folder";
    const MEDIA_REMOVE_FOLDER = "papis.media.remove_folder";
    const MEDIA_ADD_FILES_TO_FOLDER = "papis.media.add_files_to_folder";
    const MEDIA_REMOVE_FILES_TO_FOLDER = "papis.media.remove_files_to_folder";
}