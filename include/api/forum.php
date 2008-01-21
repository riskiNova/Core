<?php
////////////////////////////////////////////////////////////////////////////////
//                                                                            //
//   Copyright (C) 2008  Phorum Development Team                              //
//   http://www.phorum.org                                                    //
//                                                                            //
//   This program is free software. You can redistribute it and/or modify     //
//   it under the terms of either the current Phorum License (viewable at     //
//   phorum.org) or the Phorum License that was distributed with this file    //
//                                                                            //
//   This program is distributed in the hope that it will be useful,          //
//   but WITHOUT ANY WARRANTY, without even the implied warranty of           //
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     //
//                                                                            //
//   You should have received a copy of the Phorum License                    //
//   along with this program.                                                 //
//                                                                            //
////////////////////////////////////////////////////////////////////////////////

/**
 * This script implements the Phorum forum API.
 *
 * This API is used for managing the Phorum forum and folder hierarchy.
 * It can be used to retrieve information about the available forums and
 * folders and takes care of creating and editing them.
 *
 * This API combines forums and folders into a single API layer, because at the
 * data level, they are the same kind of entity. Folders are forums as well.
 * They just act differently, based on the "folder_flag" field.
 *
 * Below, you can find a description of the fields that are used for
 * forums and folders.
 *
 * <b>Folder fields</b>
 *
 *     - name: the name to assign to the folder. Phorum will not escape HTML
 *       code in this name, so formatting the title using HTML is allowed.
 *     - description: the description for the folder. Phorum will not escape
 *       HTML code in this name, so formatting the description using HTML
 *       is allowed.
 *     - parent_id: The folder_id of the parent folder or 0 (zero) if the
 *       folder resides in the top level root folder.
 *     - vroot: The vroot in which the folder resides. If the folder is
 *       the top level folder for a vroot, then the value for this field will
 *       be the same as the folder's forum_id.
 *     - active: Whether the folder is active/visible (1) or not (0).
 *     - template: The name of the template to use for the folder.
 *     - language: The name of the language to use for the folder.
 *
 * <b>Forum fields</b>
 *
 *     - name: the name to assign to the forum. Phorum will not escape HTML
 *       code in this name, so formatting the title using HTML is allowed.
 *     - description: the description for the forum. Phorum will not escape
 *       HTML code in this name, so formatting the description using HTML
 *       is allowed.
 *     - parent_id: The folder_id of the parent folder or 0 (zero) if the
 *       forum resides in the top level root folder.
 *     - vroot: The vroot in which the forum resides.
 *     - active: Whether the forum is active/visible (1) or not (0).
 *     - template: The name of the template to use for the folder.
 *     - language: The name of the language to use for the folder.
 *     TODO other forum fields. Maybe a different location would be better?
 *     TODO I think this should go in an "Internals" chapter in the
 *     developer docbook.
 *
 * @package    PhorumAPI
 * @subpackage ForumsAPI
 * @copyright  2007, Phorum Development Team
 * @license    Phorum License, http://www.phorum.org/license.txt
 */

if (!defined("PHORUM")) return;

// {{{ Constant and variable definitions

/**
 * The FFLD_* definitions indicate the position of the configation
 * options in the forum field definitions.
 */
define('FFLD_MS',      0);
define('FFLD_TYPE',    1);
define('FFLD_DEFAULT', 2);

/**
 * Function call flag, which tells {@link phorum_api_forums_save()}
 * that it should not save the settings to the database, but only prepare
 * the data and return the prepared data array.
 */
define('PHORUM_FLAG_PREPARE', 1);

/**
 * Function call flag, which tells {@link phorum_api_forums_save()}
 * that the provided data have to be stored in the default settings.
 */
define('PHORUM_FLAG_DEFAULTS', 2);

/**
 * This array describes folder data fields. It is mainly used internally
 * for configuring how to handle the fields and for doing checks on them.
 * Value format: <m|v>:<type>[:default]
 * m = master field; always determined by the folder's configuration data.
 * s = slave field; overridden by inheritance parent if inherid_id is set.
 */
$GLOBALS['PHORUM']['API']['folder_fields'] = array(
  'forum_id'                => 'm:int',
  'folder_flag'             => 'm:bool:1',
  'parent_id'               => 'm:int:0',
  'name'                    => 'm:string',
  'description'             => 'm:string:',
  'active'                  => 'm:bool:1',
  'forum_path'              => 'm:array',
  'display_order'           => 'm:int:0',
  'vroot'                   => 'm:int:0',
  'cache_version'           => 'm:int:0',
  'inherit_id'              => 'm:int:0',

  // Display settings.
  'template'                => 's:string:'.PHORUM_DEFAULT_TEMPLATE,
  'language'                => 's:string:'.PHORUM_DEFAULT_LANGUAGE
);

/**
 * This array describes forum data fields. It is mainly used internally
 * for configuring how to handle the fields and for doing checks on them.
 * Value format: <m|v>:<type>[:default]
 * m = master field; always determined by the folder's configuration data.
 * s = slave field; overridden by inheritance parent if inherid_id is set.
 */
$GLOBALS['PHORUM']['API']['forum_fields'] = array(
  'forum_id'                 => 'm:int',
  'folder_flag'              => 'm:bool:0',
  'parent_id'                => 'm:int:0',
  'name'                     => 'm:string',
  'description'              => 'm:string:',
  'active'                   => 'm:bool:1',
  'forum_path'               => 'm:array',
  'display_order'            => 'm:int:0',
  'vroot'                    => 'm:int:0',
  'cache_version'            => 'm:int:0',
  'inherit_id'               => 'm:int:0',

  // Display settings.
  'display_fixed'            => 's:bool:0',
  'template'                 => 's:string:'.PHORUM_DEFAULT_TEMPLATE,
  'language'                 => 's:string:'.PHORUM_DEFAULT_LANGUAGE,
  'reverse_threading'        => 's:bool:0',
  'float_to_top'             => 's:bool:1',
  'threaded_list'            => 's:int:0',
  'list_length_flat'         => 's:int:30',
  'list_length_threaded'     => 's:int:15',
  'threaded_read'            => 's:int:0',
  'read_length'              => 's:int:10',
  'display_ip_address'       => 's:bool:0',

  // Posting settings.
  'check_duplicate'          => 's:bool:1',

  // Statistics and statistics settings.
  'message_count'            => 'm:int:0',
  'thread_count'             => 'm:int:0',
  'sticky_count'             => 'm:int:0',
  'last_post_time'           => 'm:int:0',
  'count_views'              => 's:bool:1',
  'count_views_per_thread'   => 's:bool:0',

  // Permission settings.
  'moderation'               => 's:int:0',
  'email_moderators'         => 's:bool:1',
  'allow_email_notify'       => 's:bool:1',
  'pub_perms'                => 's:int:'.PHORUM_USER_ALLOW_READ,
  'reg_perms'                => 's:int:'.(
       PHORUM_USER_ALLOW_READ  |
       PHORUM_USER_ALLOW_REPLY |
       PHORUM_USER_ALLOW_EDIT  |
       PHORUM_USER_ALLOW_NEW_TOPIC
  ),

  // Attachment settings.
  'allow_attachment_types'   => 's:string:',
  'max_attachment_size'      => 's:int:0',
  'max_totalattachment_size' => 's:int:0',
  'max_attachments'          => 's:int:0',
);
// }}}

// {{{ Function: phorum_api_forums_get
/**
 * Retrieve the data for forums and/or folders in various ways.
 *
 * @param mixed $forum_ids
 *     A single forum_id or an array of forum_ids for which to retrieve the
 *     forum data. If this parameter is NULL, then the $parent_id
 *     parameter will be checked.
 *
 * @param mixed $parent_id
 *     Retrieve the forum data for all forums that have their parent_id set
 *     to $parent_id. If this parameter is NULL, then the $vroot parameter
 *     will be checked.
 *
 * @param mixed $vroot
 *     Retrieve the forum data for all forums that are in the given $vroot.
 *     If this parameter is NULL, then the $inherit_id parameter will be
 *     checked.
 *
 * @param mixed $inherit_id
 *     Retrieve the forum data for all forums that inherit their settings
 *     from the forum with id $inherit_id.
 *
 * @return mixed
 *     If the $forum_ids parameter is used and if it contains a single
 *     forum_id, then a single array containg forum data is returned or
 *     NULL if the forum was not found.
 *     For all other cases, an array of forum data arrays is returned, indexed
 *     by the forum_id and sorted by their display order. If the $forum_ids
 *     parameter is an array containing non-existant forum_ids, then the
 *     return array will have no entry available in the returned array.
 */
function phorum_api_forums_get($forum_ids = NULL, $parent_id = NULL, $vroot = NULL, $inherit_id = NULL)
{
    // Retrieve the forums/folders from the database.
    $forums = phorum_db_get_forums($forum_ids, $parent_id, $vroot, $inherit_id);

    // Filter and process the returned records.
    foreach ($forums as $id => $forum)
    {
        // Find the fields specification to use for this record.
        $fields = $forum['folder_flag']
                ? $GLOBALS['PHORUM']['API']['folder_fields']
                : $GLOBALS['PHORUM']['API']['forum_fields'];

        // Initialize the filtered data array.
        $filtered = array('folder_flag' => $forum['folder_flag'] ? 1 : 0);

        // Add fields to the filtered data.
        foreach ($fields as $fld => $fldspec)
        {
            $spec = explode(":", $fldspec);

            switch ($spec[FFLD_TYPE])
            {
                case 'int':
                    // The inherit_id field can be NULL, so we need to
                    // differentiate for NULL values here. For the other
                    // types, there are currenctly no NULL fields available.
                    $filtered[$fld] = $forum[$fld] === NULL
                                    ? NULL
                                    : (int)$forum[$fld];
                    break;

                case 'string':
                    $filtered[$fld] = $forum[$fld];
                    break;

                case 'bool':
                    $filtered[$fld] = empty($forum[$fld]) ? FALSE : TRUE;
                    break;

                case 'array':
                    $filtered[$fld] = unserialize($forum[$fld]);
                    break;

                default:
                    trigger_error(
                        'phorum_api_forums_get(): Illegal field type used: ' .
                        htmlspecialchars($fldtype),
                        E_USER_ERROR
                    );
                    break;
            }
        }

        $forums[$id] = $filtered;
    }

    if ($forum_ids === NULL || is_array($forum_ids)) {
      return $forums;
    } else {
      return isset($forums[$forum_ids]) ? $forums[$forum_ids] : NULL;
    }
}
// }}}

// {{{ Function: phorum_api_forums_save()
/**
 * This function can be used for creating and updating folders or forums and
 * for updating default forum settings.
 *
 * Here is an example for creating a forum below the folder with forum_id 1234,
 * which inherits its settings from the default forum settings.
 * <code>
 * $newforum = array(
 *     'forum_id'    => NULL,
 *     'folder_flag' => 0,
 *     'parent_id'   => 1234,
 *     'inherit_id'  => 0,
 *     'name'        => 'Foo bar baz talk'
 * );
 * $forum = phorum_api_forums_save($newforum);
 * print "The forum_id for the new forum is " . $forum['forum_id'] . "\n";
 * </code>
 *
 * This example will update some default forum settings. This will also
 * update the forums / folders that inherit their settings from the
 * default settings.
 * <code>
 * $newsettings = array(
 *     'display_ip_address' => 0,
 *     'count_views'        => 1,
 *     'language'           => 'foolang'
 * );
 * phorum_api_forums_save($newsettings, PHORUM_FLAG_DEFAULTS);
 * </code>
 *
 * @param array $data
 *     An array containing folder or forum data. This array should contain at
 *     least the field "forum_id". This field can be NULL to create a new
 *     entry with an automatically assigned forum_id (in which case you will
 *     also need to provide at least the fields "folder_flag" and "name).
 *     It can also be set to a forum_id to either update an existing entry or
 *     to create a new one with the provided forum_id.
 *
 * @param boolean $flags
 *     If the {@link PHORUM_FLAG_PREPARE} flag is set, then this function
 *     will not save the data in the database. Instead, it will only prepare
 *     the data for storage and return the prepared data.
 *     If the {@link PHORUM_FLAG_DEFAULTS} flag is set, then the data will
 *     be stored in the default forum settings.
 *
 * @return array
 *     If the {@link PHORUM_FLAG_PREPARE} is set, this function will only
 *     prepare the data for storage and return the prepared data array.
 *     Otherwise, the stored data will be returned. The main difference is
 *     that for new forums or folders, the forum_id field will be updated
 *     to the newly assigned forum_id.
 *
 * @todo when setting up inheritance, then check if there aren't any
 *       forums that are already inheriting setting from the saved forum.
 */
function phorum_api_forums_save($data, $flags = 0)
{
    // $data must be an array.
    if (!is_array($data)) {
        trigger_error(
            'phorum_api_forums_save(): $data argument is not an array',
            E_USER_ERROR
        );
        return NULL;
    }

    // Initialize data for saving default forum settings.
    if ($flags & PHORUM_FLAG_DEFAULTS)
    {
        $existing = empty($GLOBALS['PHORUM']['default_forum_options'])
                  ? NULL : $GLOBALS['PHORUM']['default_forum_options'];

        // Force a few settings to static values to have the data
        // processed correctly by the code below.
        $data['forum_id']    = NULL;
        $data['parent_id']   = 0;
        $data['inherit_id']  = NULL;
        $data['folder_flag'] = 0;
        $data['name']        = 'Default settings';
    }
    // Initialize data for saving forum settings.
    else
    {
        // We always require the forum_id field. For new forums, we want to
        // retrieve an explicit forum_id = NULL field.
        if (!array_key_exists('forum_id', $data))  {
            trigger_error(
               'phorum_api_forums_save(): missing field "forum_id" ' .
               'in the data array',
               E_USER_ERROR
            );
            return NULL;
        }
        if ($data['forum_id'] !== NULL && !is_numeric($data['forum_id'])) {
            trigger_error(
                'phorum_api_forums_save(): field "forum_id" not NULL or numerical',
                E_USER_ERROR
            );
            return NULL;
        }

        // Check if we are handling an existing or new entry.
        $existing = NULL;
        if ($data['forum_id'] !== NULL) {
            $existing = phorum_api_forums_get($data['forum_id']);
        }

        // The forum_path is a field that is generated by the API code. So we
        // pull it from the incoming data array here.
        unset($data['forum_path']);
    }

    // Create a data array that is understood by the database layer.
    // We start out with the existing record, if we have one.
    $dbdata = $existing === NULL ? array() : $existing;

    // Merge in the fields from the $data argument.
    foreach ($data as $fld => $val) {
        $dbdata[$fld] = $val;
    }

    // Some checks when we are not handling saving of default settings.
    if (!($flags & PHORUM_FLAG_DEFAULTS))
    {
        // By now, we need the folder_flag field, so we know what kind
        // of entry we are handling.
        if (!array_key_exists('folder_flag', $dbdata))  {
            trigger_error(
               'phorum_api_forums_save(): missing field "folder_flag" ' .
               'in the data array',
               E_USER_ERROR
            );
            return NULL;
        }

        // The folder_flag cannot change during the lifetime of an entry.
        if ($existing)
        {
            $check1 = $existing['folder_flag'] ? TRUE : FALSE;
            $check2 = $dbdata['folder_flag']   ? TRUE : FALSE;
            if ($check1 != $check2) {
                trigger_error(
                    "phorum_api_forums_save(): the folder_flag cannot change",
                    E_USER_ERROR
                );
                return NULL;
            }
        }
    }

    // Find the fields specification to use for this record.
    $fields = $dbdata['folder_flag']
            ? $GLOBALS['PHORUM']['API']['folder_fields']
            : $GLOBALS['PHORUM']['API']['forum_fields'];

    // A copy of the $fields array to keep track of missing fields.
    $missing = $fields;

    // Check and format the provided fields.
    foreach ($dbdata as $fld => $val)
    {
        // Make sure that a valid field name is used. We do a strict check
        // on this (in the spirit of defensive programming).
        if (!array_key_exists($fld, $fields)) {
            trigger_error(
                'phorum_api_forums_save(): Illegal field name used in ' .
                'data: ' . htmlspecialchars($fld),
                E_USER_ERROR
            );
            return NULL;
        }

        $spec = explode(':', $fields[$fld]);

        // For tracking if all required fields are available.
        unset($missing[$fld]);

        switch ($spec[FFLD_TYPE])
        {
            case 'int':
                $dbdata[$fld] = $val === NULL ? NULL : (int) $val;
                break;

            case 'string':
                $dbdata[$fld] = $val === NULL ? NULL : trim($val);
                break;

            case 'bool':
                $dbdata[$fld] = $val ? 1 : 0;
                break;

            case 'array':
                $dbdata[$fld] = is_array($val) ? serialize($val) : '';
                break;

            default:
                trigger_error(
                    'phorum_api_forums_save(): Illegal field type used: ' .
                    htmlspecialchars($spec[FFLD_TYPE]),
                    E_USER_ERROR
                );
                return NULL;
                break;
        }
    }

    // The forum_path is autogenerated and does not have to be provided.
    // Therefore, we take it out of the loop here.
    unset($missing['forum_path']);
    unset($dbdata['forum_path']);

    // Check if all required fields are available.
    if (count($missing))
    {
        // Try to fill in some default values for the missing fields.
        foreach ($missing as $fld => $fldspec)
        {
            $spec = explode(':', $fldspec);
            if (isset($spec[FFLD_DEFAULT])) {
                $dbdata[$fld] = $spec[FFLD_DEFAULT];
                unset($missing[$fld]);
            }
        }
    }

    // Apply inheritance driven settings to the data if some sort of
    // inheritance is configured. Options for this field are:
    // - NULL       : no inheritance used
    // - 0          : inherit from the default forum options
    // - <forum_id> : inherit from the forum identified by this forum_id
    if ($dbdata['inherit_id'] !== NULL)
    {
        // Inherit from the default settings.
        if ($dbdata['inherit_id'] == 0) {
            $defaults = $GLOBALS['PHORUM']['default_forum_options'];
        }
        // Inherit from a specific forum.
        else
        {
            $defaults = phorum_api_forums_get($dbdata['inherit_id']);

            // Check if the inherit_id forum was found.
            if ($defaults === NULL) {
                trigger_error(
                    'phorum_api_forums_save(): no forum found for ' .
                    'inherid_id ' . $dbdata['inherit_id'],
                    E_USER_ERROR
                );
                return NULL;
            }

            // It is only allowed to inherit settings from forums.
            if (!empty($defaults['folder_flag'])) {
                trigger_error(
                    'phorum_api_forums_save(): inherit_id ' .
                    $dbdata['inherit_id'] . ' points to a folder instead of ' .
                    'a forum. You can only inherit from forums.',
                    E_USER_ERROR
                );
            }

            // Inherited inheritance is not allowed.
            if ($defaults['inherit_id'] !== NULL) {
                trigger_error(
                    'phorum_api_forums_save(): inherit_id ' .
                    $dbdata['inherit_id'] . ' points to a forum that ' .
                    'inherits settings itself. Inherited inheritance is ' .
                    'not allowed.',
                    E_USER_ERROR
                );
            }
        }

        // Overlay our data record with the inherited settings.
        if (is_array($defaults)){
            foreach ($defaults as $fld => $value)
            {
                // We need to check if the $fld is in $fields, because we
                // could be applying forum defaults to a folder here.
                // A folder does not contain all the same fields as a forum.
                // Also check if we're handling a slave (s) field.
                if (isset($fields[$fld]) && $fields[$fld][0] == 's') {
                    $dbdata[$fld] = $value;
                    unset($missing[$fld]);
                }
            }
        }
    }

    // Check if there are any missing fields left.
    if (count($missing)) {
        trigger_error(
            'phorum_api_forums_save(): Missing field(s) in the data: ' .
            implode(', ', array_keys($missing)),
            E_USER_ERROR
        );
        return NULL;
    }

    // If we are storing default settings, then filter the data array to
    // only contain fields that are no master fields. We could store them
    // unfiltered in the database, but this provides cleaner data.
    if ($flags & PHORUM_FLAG_DEFAULTS)
    {
        $filtered = array();
        foreach ($dbdata as $fld => $value) {
            if (isset($fields[$fld]) && $fields[$fld][0] == 's') {
                $filtered[$fld] = $value;
            }
        }
        $dbdata = $filtered;
    }

    // Return the prepared data if the PHORUM_FLAG_PREPARE flag was set.
    if ($flags & PHORUM_FLAG_PREPARE) {
        return $dbdata;
    }

    // Store default settings in the database.
    if ($flags & PHORUM_FLAG_DEFAULTS)
    {
        // Create or update the settings record.
        phorum_db_update_settings(array(
            'default_forum_options' => $dbdata
        ));

        // Update the global default forum options variable, so it
        // matches the updated settings.
        $GLOBALS['PHORUM']['default_forum_options'] = $dbdata;

        // Update all forums that inherit the default settings.
        $childs = phorum_api_forums_by_inheritance(0);
        if (!empty($childs)) {
            foreach ($childs as $child) {
                phorum_api_forums_save(array(
                    'forum_id' => $child['forum_id']
                ));
            }
        }

        return $dbdata;
    }

    // Store the forum or folder in the database.
    if ($existing) {
        phorum_db_update_forum($dbdata);
    } else {
        $dbdata['forum_id'] = phorum_db_add_forum($dbdata);
    }

    // Handle changes that influence the forum tree paths.
    // We handle the updates in a separate function, because we need
    // to be able to do recursive handling for those.
    if ( !$existing ||
         ($existing['parent_id'] != $dbdata['parent_id']) ||
         ($existing['vroot']     != $dbdata['vroot']) ||
         ($existing['name']      != $dbdata['name']) ) {

        $recurse = $existing ? TRUE : FALSE;
        if (!phorum_api_forums_update_path($dbdata, $recurse)) return NULL;
    }

    // Handle cascading of inherited settings.
    // Inheritance is only possible from existing forums that do not inherit
    // settings themselves. So only if the currently saved entry does match
    // those criteria, we might have to cascade.
    if ($existing &&
        $existing['folder_flag'] == 0 &&
        $existing['inherit_id'] === NULL)
    {
        // Find the forums and folders that inherit from this forum.
        $childs = phorum_api_forums_by_inheritance($existing['forum_id']);

        // If there are child forums, then update their inherited settings.
        if (!empty($childs)) {
            foreach ($childs as $child) {
                phorum_api_forums_save(array(
                    'forum_id' => $child['forum_id']
                ));
            }
        }
    }

    return $dbdata;
}
// }}}

// {{{ Function: phorum_api_forums_update_path()
/**
 * This function can be used to (recursively) update forum_path fields.
 *
 * The function is internally used by Phorum to update the paths that are
 * stored in the "forum_path" field of the forums table. Under normal
 * circumstances, this function will be called when appropriate by the
 * {@link phorum_api_forums_save()} function.
 *
 * @param array $forum
 *     A forum data array. The forum_path will be updated for this forum.
 *     The array requires at least the fields: forum_id, parent_id,
 *     folder_flag and vroot.
 *
 * @param boolean $recurse
 *     If this parameter is set to TRUE (the default), then recursive
 *     path updates will be done. The function will walk down the folder/forum
 *     tree to update all paths.
 *
 * @return mixed
 *     On failure trigger_error() will be called. If some error handler
 *     does not stop script execution, this function will return NULL.
 *     On success, an updated $forum array will be returned.
 */
function phorum_api_forums_update_path($forum, $recurse = TRUE)
{
    // Check if the parent_id is valid.
    if ($forum['parent_id'] != 0)
    {
        $parent = phorum_api_forums_get($forum['parent_id']);

        // Check if the parent was found.
        if ($parent === NULL) {
            trigger_error(
                'phorum_api_forums_save(): parent_id ' .
                $forum['parent_id'] . ' point to a folder that does ' .
                'not exist.',
                E_USER_ERROR
            );
            return NULL;
        }

        // Check if the parent is a folder.
        if (!$parent['folder_flag']) {
            trigger_error(
                'phorum_api_forums_save(): parent_id ' .
                $forum['parent_id'] . ' does not point to a folder. ' .
                'You can only put forums/folders inside folders.',
                E_USER_ERROR
            );
            return NULL;
        }
    }

    // If this is not a vroot folder, then the $forum needs to inherit
    // its vroot from its parent. We'll silently fix inconsitencies
    // in this info here.
    if (!$forum['folder_flag'] || $forum['vroot'] != $forum['forum_id']) {
        $forum['vroot'] = $parent['vroot'];
    }

    // Check if the vroot is valid.
    if ($forum['vroot'] != 0)
    {
        // Retrieve the info from the vroot.
        $vroot = phorum_api_forums_get($forum['vroot']);

        // Check if the vroot was found.
        if ($vroot === NULL) {
            trigger_error(
                'phorum_api_forums_save(): vroot ' .
                $forum['vroot'] . ' point to a folder that does ' .
                'not exist.',
                E_USER_ERROR
            );
            return NULL;
        }

        // Check if the vroot is a folder.
        if (!$vroot['folder_flag']) {
            trigger_error(
                'phorum_api_forums_save(): vroot ' .
                $forum['vroot'] . ' does not point to a folder. ' .
                'Only folders can be vroots.',
                E_USER_ERROR
            );
            return NULL;
        }

        // Check if the vroot folder is setup as a vroot.
        if ($vroot['vroot'] != $vroot['forum_id']) {
            trigger_error(
                'phorum_api_forums_save(): vroot ' .
                $forum['vroot'] . ' points to a folder that is  not ' .
                'setup as a vroot folder.',
                E_USER_ERROR
            );
            return NULL;
        }
    }

    // Rebuild the forum_path for this forum.
    $path = phorum_api_forums_build_path($forum['forum_id']);
    $forum['forum_path'] = $path;
    phorum_db_update_forum(array(
        'vroot'      => $forum['vroot'],
        'forum_id'   => $forum['forum_id'],
        'forum_path' => $forum['forum_path']
    ));

    // Cascade path updates down the forum tree. This is only
    // applicable to folders and if recursion is enabled.
    if ($forum['folder_flag'] && $recurse)
    {
        // Find the forums and folders that are contained by this folder.
        $childs = phorum_api_forums_by_parent_id($forum['forum_id']);

        // If there are childs, then update their vroot (which might have
        // changed) and save them to have the path updated.
        if (!empty($childs)) {
            foreach ($childs as $child){
                $child['vroot'] = $forum['vroot'];
                if (!phorum_api_forums_update_path($child)) {
                    return NULL;
                }
            }
        }
    }

    return $forum;
}
// }}}

// {{{ Function: phorum_api_forums_build_path()
/**
 * This function can be used for building the folder paths that lead up to
 * forums/folders.
 *
 * The function is internally used by Phorum to build the paths that are stored
 * in the "forum_path" field of the forums table. If you need access to the
 * path for a folder or forum, then do not call this function for retrieving
 * that info, but look at the "forum_path" field in the forum or folder
 * info instead.
 *
 * @param mixed $forum_id
 *     If $forum_id is NULL, then the paths for all available forums and
 *     folders will be built. Otherwise, only the path for the requested
 *     forum_id is built.
 *
 * @return array
 *     If the $forum_id parameter is a single forum_id, then a single path
 *     is returned. If it is NULL, then an array or paths is returned, indexed
 *     by the forum_id for which the path was built.
 *     Each path is an array, containing the nodes in the path
 *     (key = forum_id, value = name). The first element in a path array will
 *     be the (v)root and the last element the forum or folder for which the
 *     path was built.
 *
 *     Note: the root node (forum_id = 0) will also be returned in the
 *     data when using NULL or 0 as the $forum_id argument. This is however a
 *     generated node for which no database record exists. So if you are using
 *     this functions return data for updating folders in the database, then
 *     beware to skip the forum_id = 0 root node.
 */
function phorum_api_forums_build_path($forum_id = NULL)
{
    $paths = array();

    // The forum_id = 0 root node is not in the database.
    // Here, we create a representation for that node that will work.
    $root = array(
        'vroot'    => 0,
        'forum_id' => 0,
        'name'     => $GLOBALS['PHORUM']['title']
    );

    // If we are going to update the paths for all nodes, then we pull
    // in our full list of forums and folders from the database. If we only
    // need the path for a single node, then the node and all its parent
    // nodes are retrieved using single calls to the database.
    if ($forum_id === NULL) {
        $nodes = phorum_db_get_forums();
        $nodes[0] = $root;
    } else {
        if ($forum_id == 0) {
            $nodes = array(0 => $root);
        } else {
            $nodes = phorum_db_get_forums($forum_id);
        }
    }

    // Build the paths for the retrieved node(s).
    foreach($nodes as $id => $node)
    {
        $path = array();

        while (TRUE)
        {
            // Add the node to the path.
            $path[$node['forum_id']] = $node['name'];

            // Stop building when we hit a (v)root.
            if ($node['forum_id'] == 0 ||
                $node['vroot'] == $node['forum_id']) break;

            // Find the parent node. The root node (forum_id = 0) is special,
            // since that one is not in the database. We create an entry on
            // the fly for that one here.
            if ($node['parent_id'] == 0) {
                $node = $root;
            } elseif ($forum_id !== NULL) {
                $tmp = phorum_db_get_forums($node['parent_id']);
                $node = $tmp[$node['parent_id']];
            } else {
                $node = $nodes[$node['parent_id']];
            }
        }

        // Reverse the path, since we have been walking up the path here.
        // For the parts of the application that use this data, it's more
        // logical if the root nodes come first in the path arrays.
        $paths[$id] = array_reverse($path, TRUE);
    }

    if ($forum_id === NULL) {
        return $paths;
    } else {
        return isset($paths[$forum_id]) ? $paths[$forum_id] : NULL;
    }
}
// }}}

// {{{ Function: phorum_api_forums_change_order()
/**
 * Change the displaying order for forums and folders in a certain folder.
 *
 * @param integer $folder_id
 *     The forum_id of the folder in which to change the display order.
 *
 * @param integer $forum_id
 *     The id of the forum or folder to move.
 *
 * @param string $movement
 *     This field determines the type of movement to apply to the forum
 *     or folder. This can be one of:
 *     - "up": Move the forum or folder $value positions up
 *     - "down": Move the forum or folder $value permissions down
 *     - "pos": Move the forum or folder to position $value
 *     - "start": Move the forum or folder to the start of the list
 *     - "end": Move the forum or folder to the end of the list
 *
 * @param mixed $value
 *     This field specifies a value for the requested type of movement.
 *     An integer value is only needed for movements "up", "down" and "pos".
 *     For other movements, this parameter can be omitted.
 */
function phorum_api_forums_change_order($folder_id, $forum_id, $movement, $value = NULL)
{
    settype($folder_id, 'int');
    settype($forum_id, 'int');
    if ($value !== NULL) settype($value, 'int');

    // Get the forums for the specified folder.
    $forums = phorum_api_forums_by_folder($folder_id);

    // Prepare the forum list for easy ordering.
    $current_pos = NULL;
    $pos = 0;
    $forum_ids = array();
    foreach ($forums as $forum) {
        if ($forum['forum_id'] == $forum_id) $current_pos = $pos;
        $forum_ids[$pos++] = $forum['forum_id'];
    }

    $pos--;  // to make this the last index position in the array.

    // If the forum_id is not in the folder, then return right away.
    if ($current_pos === NULL) return;

    switch ($movement)
    {
        case "up":    $new_pos = $current_pos - $value; break;
        case "down":  $new_pos = $current_pos + $value; break;
        case "pos":   $new_pos = $value;                break;
        case "start": $new_pos = 0;                     break;
        case "end":   $new_pos = $pos;                  break;

        default:
            trigger_error(
                "phorum_api_forums_change_order(): " .
                "Illegal \$momement parameter \"$movement\" used",
                E_USER_ERROR
            );
    }

    // Keep the new position within boundaries.
    if ($new_pos < 0) $new_pos = 0;
    if ($new_pos > $pos) $new_pos = $pos;
    // No order change, then return.
    if ($new_pos == $current_pos) return;

    // Reorder the forum_ids array to represent the order change.
    $new_order = array();
    for ($i = 0; $i <= $pos; $i++)
    {
        if ($i == $current_pos) continue;
        if ($i == $new_pos) {
            if ($i < $current_pos) {
                $new_order[] = $forum_id;
                $new_order[] = $forum_ids[$i];
            } else {
                $new_order[] = $forum_ids[$i];
                $new_order[] = $forum_id;
            }
        } else {
            $new_order[] = $forum_ids[$i];
        }
    }

    // Loop through all the forums and update the ones that changed.
    // We have to look at them all, because the default value for
    // display order is 0 for all forums. So, in an unsorted folder,
    // all the display order values are set to 0 until you move one.
    foreach ($new_order as $display_order => $forum_id) {
        if ($forums[$forum_id]['display_order'] != $display_order) {
            phorum_db_update_forum(array(
                'forum_id'      => $forum_id,
                'display_order' => $display_order
            ));
        }
    }
}
// }}}

// ------------------------------------------------------------------------
// Alias functions (useful shortcut calls to the main file api functions).
// ------------------------------------------------------------------------

// {{{ Function: phorum_api_forums_by_folder()
/**
 * Retrieve data for all direct descendant forums and folders within a folder.
 *
 * @param integer $folder_id
 *     The forum_id of the folder for which to retrieve the forums.
 *
 * @return array
 *     An array of forums and folders, index by the their forum_id and sorted
 *     by their display order.
 */
function phorum_api_forums_by_folder($folder_id = 0)
{
   return phorum_api_forums_get(NULL, $folder_id);
}
// }}}

// {{{ Function: phorum_api_forums_by_parent_id()
/**
 * Retrieve data for all child forums and folders for a given parent folder.
 *
 * @param integer $parent_id
 *     The parent_id of the folder for which to retrieve the forums.
 *
 * @return array
 *     An array of forums and folders, index by the their forum_id and sorted
 *     by their display order.
 */
function phorum_api_forums_by_parent_id($parent_id = 0)
{
    return phorum_api_forums_get(NULL, $parent_id);
}
// }}}

// {{{ Function: phorum_api_forums_by_vroot()
/**
 * Retrieve data for all forums and folders that belong to a certain vroot.
 *
 * @param integer $vroot_id
 *     The forum_id of the vroot for which to retrieve the forums.
 *
 * @return array
 *     An array of forums and folders, index by the their forum_id and sorted
 *     by their display order.
 */
function phorum_api_forums_by_vroot($vroot_id = 0)
{
    return phorum_api_forums_get(NULL, NULL, $vroot_id);
}
// }}}

// {{{ Function: phorum_api_forums_by_inheritance()
/**
 * Retrieve data for all forums and folders that inherit their settings
 * from a certain forum.
 *
 * @param integer $forum_id
 *     The forum_id for which to check what forums inherit its setting.
 *
 * @return array
 *     An array of forums and folders, index by the their forum_id and sorted
 *     by their display order.
 */
function phorum_api_forums_by_inheritance($forum_id = 0)
{
    return phorum_api_forums_get(NULL, NULL, NULL, $forum_id);
}
// }}}

?>