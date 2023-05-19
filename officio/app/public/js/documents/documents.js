function reload_keep_expanded(tree) {
    // save expanded nodes
    var expNodes = [];
    tree.getRootNode().cascade(function (node) {
        if (node.isExpanded() && node.attributes.path_hash) {
            expNodes.push(node.attributes.path_hash);
        }
    });

    // save selection
    var selNode = tree.getSelectionModel().getSelectedNode();
    if (selNode) {
        selNode = selNode.attributes.path_hash;
    }

    tree.getLoader().on('load', function () {
        // apply expanded
        tree.getRootNode().cascade(function (node) {
            for (var i = 0; i < expNodes.length; i++) {
                if (expNodes[i] === node.attributes.path_hash) {
                    node.expand(false, false);
                    break;
                }
            }
        });

        // apply selection
        tree.getRootNode().cascade(function (node) {
            if (selNode === node.attributes.path_hash) {
                node.select();

                if (typeof tree.setAllowedItems === 'function') {
                    tree.setAllowedItems(node, !isSelectedFiles(tree));
                }
            }
        });
    }, this, {single: true});

    tree.getRootNode().reload();
}

//check or node is folder
function isFolder(node) {
    if (node && node.attributes) {
        return node.attributes.folder;
    } else if (node.folder) {
        return node.folder
    }
    return false;
}

//node is checked
function isChecked(node) {
    return node && node.getUI().isChecked();
}

//return focused node (with blue background)
function getFocusedNode(tree) {
    return tree ? tree.getSelectionModel().getSelectedNode() : false;
}

//return node ID
function getNodeId(node) {
    if (node && node.attributes) {
        return node.isRoot ? 'root' : node.attributes.el_id;
    } else if (node && node.el_id) {
        return node.el_id
    }
    return false;
}

function getNodeHash(node) {
    if (node && node.attributes) {
        return node.attributes.path_hash;
    } else if (node && node.path_hash) {
        return node.path_hash
    }
    return false;
}

//check / uncheck node
function switchCheckNode(node) {
    node.getUI().toggleCheck(!isChecked(node));
}

//return focused Node ID
function getFocusedNodeId(tree) {
    return getNodeId(getFocusedNode(tree));
}

//return node File Name
function getNodeFileName(node) {
    return (node && node.attributes) ? node.attributes.filename : (node && node.filename ? node.filename : '');
}

//return focused File
function getFocusedFile(tree) {
    var node = getFocusedNode(tree);
    return (node && !isFolder(node)) ? node : false;
}

//return focused File ID
function getFocusedFileId(tree) {
    var file = getFocusedFile(tree);
    return file ? getNodeId(file) : false;
}

//return focused Folder ID
function getFocusedFolderId(tree) {
    var node = getFocusedNode(tree);
    if (node) {
        if (isFolder(node)) {
            return getNodeId(node);
        } else {
            return getNodeId(node.parentNode);
        }
    }
    return false;
}

function getSelectedNodes(tree, booOnlyId) {
    var files = [];
    var checked = tree.getChecked();
    if (checked.length === 0) { //if no checked items get selected items
        var file = (booOnlyId ? getFocusedNodeId(tree) : getFocusedNode(tree));
        if (file) {
            files = [file];
        }
    }
    else {//else download all checked items
        for (var i = 0; i < checked.length; i++) {
            files.push(booOnlyId ? getNodeId(checked[i]) : checked[i]);
        }
    }

    return files;
}

function isSelectedFiles(tree) {
    //all selected nodes
    var nodes = getSelectedFiles(tree, false);
    return nodes.length > 0;
}

//  If any one element was checked then returns an array of checked nodes id's. Otherwise, returns the selected node id.
function getSelectedFiles(tree, booOnlyId) {
    var nodes = getSelectedNodes(tree);

    var arr = [];
    for (var i = 0; i < nodes.length; i++) {
        if (!isFolder(nodes[i])) {
            arr.push(booOnlyId ? getNodeId(nodes[i]) : nodes[i]);
        }
    }

    return arr;
}

//Find Tree Child By Attributes Node ID
function findChildByNodeId(tree, node_id) {
    function findChildByNodeIdCore(node, node_id, found) {
        if (typeof node !== 'undefined') {
            for (var i = 0; i < node.length; i++) {
                if (found) {
                    return found;
                }
                if (getNodeId(node[i]) == node_id) {
                    return node[i];
                }
                var children = node[i].attributes ? node[i].attributes.children : node[i].children;
                found = findChildByNodeIdCore(children, node_id, found);
            }
        }

        return found;
    }

    var root = tree.getRootNode();

    //is root node
    if (root.id == node_id || node_id == 'root') {
        return root;
    }

    return findChildByNodeIdCore(root.childNodes, node_id, false);
}

// Formatting File Size
function renderDocFileSize(rec) {
    return rec ? '<b>' + rec + '</b>' : '';
}
// Formatting File Name
function renderDocFileName(val, i, rec, booShowTooltip) {
    return val ? String.format('<span ext:qtip="{0}">{1}</span>', !booShowTooltip || isFolder(i) ? '' : val, val) : '';
}

//check or file is exists in tree
function checkIfFileExists(tree, checkFolder, checkFileName, booDocumentsChecklistTab, dependentId) {
    var booAskRewrite = false;
    var foundElement = findChildByNodeId(tree, checkFolder);
    if (foundElement.attributes) {
        foundElement.eachChild(function (subnode) {
            if (booDocumentsChecklistTab) {
                if (subnode.attributes.text == checkFileName && subnode.attributes.dependent_id == dependentId) {
                    booAskRewrite = true;
                }
            } else {
                if (subnode.attributes.filename == checkFileName) {
                    booAskRewrite = true;
                }
            }
        });
    } else {
        for (var i = 0; i < foundElement.children.length; i++) {
            var subnode = foundElement.children[i];
            if (booDocumentsChecklistTab) {
                if (subnode.text == checkFileName && subnode.dependent_id == dependentId) {
                    booAskRewrite = true;
                }
            } else {
                if (subnode.filename == checkFileName) {
                    booAskRewrite = true;
                }
            }
        }
    }

    return booAskRewrite;
}

function initTreeDragAndDrop(tree, memberId) {
    var bbar = tree.getBottomToolbar();

    var bbarProgress = new Ext.ProgressBar({
        text:  'Uploading...',
        width: 250,
        hidden: true
    });

    var bbarProgressCancelButton = new Ext.Button({
        text:    'Cancel',
        width:   80,
        style:   'padding-left: 10px;',
        hidden: true,
        handler: function () {
            treeDropZone.removeAllFiles(true);
        }
    });

    bbar.add(bbarProgress);
    bbar.add(bbarProgressCancelButton);
    bbar.doLayout();

    var toggleBBarItems = function (booShow) {
        bbarProgress.setVisible(booShow);
        bbarProgressCancelButton.setVisible(booShow);
    };

    Dropzone.autoDiscover = false;

    var treeDropZone = new Dropzone('#' + tree.id + ' .x-tree-lines', {
        url:              tree.destinationUrl ? tree.destinationUrl : topBaseUrl + '/' + tree.destination + '/index/files-upload',
        uploadMultiple:   false,
        autoProcessQueue: false,
        paramName:        'docs-upload-file',
        parallelUploads:  50,
        maxFiles:         50,
        timeout:          1000 * 60 * 5, // 5 minutes
        maxFilesize:      post_max_size / 1024 / 1024, // MB
        dictFileTooBig:   'File is too big ({{filesize}}MiB).',

        processedFileCount: 0,
        dragged: null,

        params: {
            member_id: memberId,
            folder_id: '',
            files:     0
        },

        init: function () {
            var _this = this;

            this.on('dragstart', function (e) {
                _this.options.dragged = e.target;
                if (!empty(e.target.dataset) && !empty(e.target.dataset.downloadurl)) {
                    e.dataTransfer.setData('DownloadURL', e.target.dataset.downloadurl);
                }
            });

            // Automatically expand the folder or root folder (if hover about the file)
            this.on('dragover', function (e) {
                var nodeEl = $(e.target).closest('.x-tree-node-el');

                if (nodeEl.length) {
                    var nodeId = nodeEl.attr('ext:tree-node-id');
                    var node   = tree.getNodeById(nodeId);

                    if (isFolder(node)) {
                        node.expand(true);
                        node.select();
                    } else {
                        node.parentNode.select();
                    }
                }
            });

            // Show errors if any
            this.on('error', function (file, response) {
                Ext.ux.PopupMessage.msg(_('Error'), file.name + ': ' + response.error);
            });

            this.on('drop', function (e) {
                _this.options.params.files       = 0;
                _this.options.processedFileCount = 0;

                // Identify if dropped files into the folder (or to the file's parent folder) and if have sufficient access rights
                var booCanUpload = false;
                var nodeEl       = $(e.target).closest('.x-tree-node-el');
                var targetNode;
                if (nodeEl.length) {
                    var nodeId = nodeEl.attr('ext:tree-node-id');
                    targetNode   = tree.getNodeById(nodeId);

                    if (!isFolder(targetNode)) {
                        targetNode = targetNode.parentNode;
                    }

                    _this.options.params.folder_id = getNodeId(targetNode);
                    if (targetNode.attributes.allowRW) {
                        booCanUpload = true;
                    }
                }

                if (booCanUpload) {
                    var files = [];

                    // Make sure that we'll skip "fake files in Chrome"
                    // e.dataTransfer.files should be empty if no files were provided (e.g. a simple drag and drop)
                    var arrItems = e.dataTransfer.items;
                    for (var i = 0; i < arrItems.length; i++) {
                        if (arrItems[i].kind === 'file') {
                            files.push(arrItems[i].getAsFile());
                        }
                    }

                    if (files.length) {
                        setTimeout(function () {
                            var arrRejected = treeDropZone.getRejectedFiles();
                            if (!arrRejected.length) {
                                var countDuplicates = 0;
                                var only_filename   = '';
                                for (var j = 0; j < files.length; j++) {
                                    _this.options.params.files += 1;

                                    var str_filename = files[j].name;
                                    only_filename    = files[j].name;
                                    if (str_filename.lastIndexOf('/') > 0) {
                                        only_filename = str_filename.substring(str_filename.lastIndexOf('/') + 1, str_filename.length);
                                    } else if (str_filename.lastIndexOf('\\') > 0) {
                                        only_filename = str_filename.substring(str_filename.lastIndexOf('\\') + 1, str_filename.length);
                                    }

                                    if (checkIfFileExists(tree, _this.options.params.folder_id, only_filename)) {
                                        countDuplicates++;
                                    }
                                }

                                // Show confirmation if there are duplicates
                                if (countDuplicates > 0) {
                                    var msg = String.format(countDuplicates === 1 ? '{1} already exists, would you like to overwrite it?' :
                                        '{0} documents already exist, would you like to overwrite them?', countDuplicates, only_filename);
                                    Ext.Msg.confirm('Please confirm', msg, function (btn) {
                                        if (btn === 'yes') {
                                            toggleBBarItems(true);
                                            treeDropZone.processQueue();
                                        } else {
                                            treeDropZone.removeAllFiles();
                                            _this.options.params.files = 0;
                                        }
                                    });
                                } else {
                                    toggleBBarItems(true);
                                    treeDropZone.processQueue();
                                }
                            } else {
                                treeDropZone.removeAllFiles();
                                _this.options.params.files = 0;
                            }
                        }, 100);
                    } else {
                        var draggedNodeEl = $(_this.options.dragged).closest('.x-tree-node-el');
                        if (draggedNodeEl.length) {
                            var draggedNodeId = draggedNodeEl.attr('ext:tree-node-id');
                            var draggedNode   = tree.getNodeById(draggedNodeId);

                            // Denied move to the current folder and to own subfolders
                            if (getNodeId(draggedNode.parentNode) !== _this.options.params.folder_id &&
                                getNodeId(draggedNode) !== _this.options.params.folder_id &&
                                draggedNode.attributes.allowDrag && targetNode.attributes.allowDrop &&
                                !checkParentNodeMatch(targetNode, draggedNode)) {
                                dragAndDrop(tree, draggedNode, memberId, _this.options.params.folder_id);
                            }
                        }
                    }
                }
            });

            this.on('complete', function () {
                _this.options.processedFileCount++;

                // Update progressbar
                var booDone = _this.options.processedFileCount >= treeDropZone.files.length;
                var status  = booDone ? 'Done!' : String.format('Uploading {0} of {1}...', _this.options.processedFileCount, treeDropZone.files.length);
                bbarProgress.updateProgress(_this.options.processedFileCount / treeDropZone.files.length, status, true);

                if (booDone) {
                    setTimeout(function () {
                        toggleBBarItems(false);
                        bbarProgress.updateProgress(0, 'Uploading...', false);
                    }, 1000);
                }
            });

            this.on('queuecomplete', function () {
                // When all is done - refresh the tree
                if (!empty(_this.options.processedFileCount)) {
                    reload_keep_expanded(tree);

                    if (Ext.getCmp('general-notes-grid-' + memberId)) {
                        Ext.getCmp('general-notes-grid-' + memberId).store.reload();
                    }

                    treeDropZone.removeAllFiles();
                }
            });
        }
    });
}

function checkParentNodeMatch(targetNode, draggedNode) {
    var booMatch = getNodeId(targetNode.parentNode) === getNodeId(draggedNode);

    var targetParentNode = targetNode.parentNode;

    if (!booMatch && !empty(targetParentNode)) {
        return checkParentNodeMatch(targetParentNode, draggedNode);
    } else {
        return booMatch;
    }
}

//prepare to upload file
function uploadFile(tree, member_id) {
    var node = getFocusedNode(tree);
    if (node && !isFolder(node)) {
        node = node.parentNode;
    }

    if (node) {
        var dialog = new MultiUploadDialog({
            settings: {
                tree: tree,
                member_id: empty(member_id) ? 0 : member_id,
                node: node,
                folder_name: getNodeFileName(node)
            }
        });

        dialog.show();
    } else {
        Ext.simpleConfirmation.warning('Please select folder to upload');
    }
}

function newfile(mydocsTabs, tree, memberId, booPreview) {
    var folder_id = getFocusedFolderId(tree);
    if (!folder_id) {
        Ext.simpleConfirmation.warning('Please select folder to create new file');
        return false;
    }

    var name = new Ext.form.TextField({
        fieldLabel: 'File name',
        regex: /^(?:^[^ \\\/:*?""<>|]+([ ]+[^ \\\/:*?""<>|]+)*$)$/i,
        regexText: 'Invalid file name',
        allowBlank: false,
        width: 215
    });

    var type = new Ext.form.ComboBox({
        fieldLabel: 'File type',
        allowBlank: false,
        width: 215,
        store: new Ext.data.SimpleStore({
            fields: ['type_id', 'type_name'],
            data: [
                ['txt', 'Text File (*.txt)'],
                ['doc', 'Word Document (*.doc)'],
                ['docx', 'Word Document (*.docx)'],
                ['sxw', 'OpenOffice (*.sxw)'],
                ['rtf', 'Rich Text Format (*.rtf)'],
                ['html', 'HTML Document (*.html)']
            ]
        }),
        mode: 'local',
        displayField: 'type_name',
        valueField: 'type_id',
        value: 'doc',
        triggerAction: 'all',
        selectOnFocus: true,
        editable: false
    });

    var pan = new Ext.FormPanel({
        bodyStyle: 'padding:5px;',
        labelWidth: 60,
        items: [name, type]
    });

    var win = new Ext.Window({
        title: 'New File',
        modal: true,
        width: 300,
        autoHeight: true,
        resizable: false,
        items: pan,
        buttons: [
            {
                text: 'Cancel',
                handler: function () {
                    win.close();
                }
            }, {
                text: 'Create',
                cls: 'orange-btn',
                handler: function () {
                    if (pan.getForm().isValid()) {
                        var filename = name.getValue();
                        var filetype = type.getValue();
                        var filenameWithExt = filename + '.' + filetype;

                        var fileExists = checkIfFileExists(tree, folder_id, filenameWithExt);
                        if (fileExists) {
                            Ext.simpleConfirmation.warning('A document with this name already exists. Please change the name and try again.');
                            return false;
                        }

                        win.getEl().mask('Creating...');
                        Ext.Ajax.request({
                            url: topBaseUrl + '/' + tree.destination + '/index/new-file',
                            params: {
                                folder_id: folder_id,
                                name: Ext.encode(filename),
                                type: filetype,
                                member_id: memberId
                            },

                            success: function (result) {
                                var resultData = Ext.decode(result.responseText);
                                if (resultData.success && resultData.file_id) {
                                    tree.owner.openPreview(resultData.file_id, resultData.path_hash, filenameWithExt, false, memberId);
                                    reload_keep_expanded(tree);
                                    win.close();
                                } else {
                                    Ext.simpleConfirmation.error('New file was not created. Please try again later.');
                                }

                                win.getEl().unmask();
                            },

                            failure: function () {
                                Ext.simpleConfirmation.error('New file cannot be added. Please try again later.');
                                win.getEl().unmask();
                            }
                        });
                    }
                    return true;
                }
            }
        ]
    });

    win.show();
    return true;
}

//download selected file(s)
function downloadFiles(tree, memberId) {
    var files = getSelectedFiles(tree, true);

    if (files)
        for (var i = 0; i < files.length; i++) {
            submit_hidden_form(
                topBaseUrl + '/' + tree.destination + '/index/get-file',
                {
                    id: files[i],
                    member_id : memberId
                }
            );
        }
}

function zipDocuments(tree, type, memberId, what) {
    var getAllFilesPaths = function (arrObjects) {
        var arrFilesPaths = [];
        if (arrObjects.length) {
            for (var i = 0; i < arrObjects.length; i++) {
                if (arrObjects[i]['leaf']) {
                    arrFilesPaths.push(arrObjects[i]['el_id'])
                } else if (arrObjects[i]['children'] && arrObjects[i]['iconCls'] != 'las la-share-alt') {
                    var subFiles = getAllFilesPaths(arrObjects[i]['children']);
                    for (var j = 0; j < subFiles.length; j++) {
                        arrFilesPaths.push(subFiles[j])
                    }
                }
            }
        }

        return arrFilesPaths;
    };

    var filesAndFolders = [];
    switch (what) {
        case 'all':
            filesAndFolders = getAllFilesPaths(tree.loader.loadedData);
            break;

        case 'menu':
            filesAndFolders = [getFocusedNodeId(tree)];
            break;

        case 'toolbar':
            filesAndFolders = getSelectedNodes(tree, true);
            break;

        default:
            filesAndFolders = [];
            break;
    }

    if (filesAndFolders && filesAndFolders.length) {
        var url = topBaseUrl + '/' + tree.destination + '/index/create-zip';
        submit_hidden_form(url, {memberId: memberId, type: type, filesAndFolders: Ext.encode(filesAndFolders)});
    } else {
        Ext.simpleConfirmation.warning('Please select the objects that you need to compress as zip.');
    }
}

//open (edit|preview) selected file(s)
function open_selected_files(myDocsTabs, tree, booPreview, memberId) {
    var nodes = getSelectedFiles(tree, false);
    if (nodes) {
        var filesCount = 0;
        for (var i = 0; i < nodes.length; i++) {
            if (!isFolder(nodes[i])) {
                filesCount++;
                tree.owner.openPreview(getNodeId(nodes[i]), getNodeHash(nodes[i]), getNodeFileName(nodes[i]), booPreview, memberId);
            }
        }

        if (empty(filesCount)) {
            Ext.simpleConfirmation.warning(_('Please select at least one document to preview.'));
        }
    }
}

//open (edit|preview) focused file
function open_focused_file(myDocsTabs, tree, node, booPreview) {
    if (node && !isFolder(node)) {
        tree.owner.openPreview(getNodeId(node), getNodeHash(node), getNodeFileName(node), booPreview, tree.memberId);
    }
}

function renameDocument(tree, type, member_id) {
    var node = getFocusedNode(tree);
    if (isFolder(node)) {
        if (node.attributes.allowDeleteFolder) {
            if (node.attributes.isDefaultFolder) {
                var question;
                var folderName = getNodeFileName(node);
                if (is_client) {
                    question = String.format(
                        _('WARNING: <i>{0}</i> is a default folder. If it will be renamed - you will not have access to it anymore.<br/><br/>Are you sure you want to rename this folder?'),
                        folderName
                    );
                } else {
                    question = String.format(
                        _('WARNING: <i>{0}</i> is a default folder. If it will be renamed - clients will not have access to it anymore.<br/><br/>Are you sure you want to rename this folder?'),
                        folderName
                    );
                }

                Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                    if (btn === 'yes') {
                        renameFolder(tree, node, type, member_id);
                    }
                });
            } else {
                renameFolder(tree, node, type, member_id);
            }
        } else {
            Ext.simpleConfirmation.warning(_('This folder cannot be renamed.'));
        }
    } else {
        renameFile(tree, node, type, member_id);
    }

    return node;
}

//rename file
function renameFile(tree, node, type, member_id) {
    //get focused file ID
    if (node) {
        var file_id = getNodeId(node);
        var fileHash = getNodeHash(node);
        var filename = getNodeFileName(node);
        var name = filename.substr(0, filename.lastIndexOf('.'));
        var ext = filename.substr(filename.lastIndexOf('.') + 1);

        //prompt to enter new file name
        Ext.Msg.prompt('Rename', 'File name:', function (btn, text) {
            if (btn === 'ok') {
                //validate file name
                if (!isValidFileName(text)) {
                    Ext.simpleConfirmation.error('Incorrect symbols in file name');
                    return false;
                }

                var newFilename = text + '.' + ext;

                //send request to save filename in DB
                Ext.getBody().mask('Processing...');
                Ext.Ajax.request({
                    url: topBaseUrl + '/' + tree.destination + '/index/rename-file',
                    params: {
                        file_id: file_id,
                        filename: Ext.encode(newFilename),
                        member_id: Ext.encode(member_id),
                        type: Ext.encode(type)
                    },

                    success: function (result) {
                        result = Ext.decode(result.responseText);
                        if (result.success) {
                            reload_keep_expanded(tree);
                            tree.owner.documentsPreviewPanel.closePreviews([fileHash]);
                        } else {
                            Ext.simpleConfirmation.error(result.msg);
                        }

                        Ext.getBody().unmask();
                    },

                    failure: function () {
                        Ext.simpleConfirmation.error('Document can\'t be renamed. Please try again later.');
                        Ext.getBody().unmask();
                    }
                });
            }
            return true;
        }, null, false, name);
    }
}

function dragAndDrop(tree, node, member_id, folder_id) {
    Ext.getBody().mask('Loading...');
    Ext.Ajax.request({
        url: topBaseUrl + '/' + tree.destination + '/index/drag-and-drop',
        params: {
            file_id: getNodeId(node),
            folder_id: folder_id || getNodeId(node.parentNode),
            member_id: member_id
        },
        success: function () {
            reload_keep_expanded(tree);
            Ext.getBody().unmask();
        },
        failure: function () {
            Ext.simpleConfirmation.error('Can\'t drop this Documents');
            Ext.getBody().unmask();
        }
    });
}

function addDefaultFolders(tree, member_id) {
    Ext.getBody().mask('Creating default folders, please wait...');
    Ext.Ajax.request({
        url: topBaseUrl + '/' + tree.destination + '/index/add-default-folders',
        params: {
            member_id: member_id
        },

        success: function (result) {
            result = Ext.decode(result.responseText);
            if (result.success) {
                reload_keep_expanded(tree);

                Ext.simpleConfirmation.success(result.message);
            } else {
                Ext.simpleConfirmation.error(result.message);
            }

            Ext.getBody().unmask();
        },

        failure: function () {
            Ext.simpleConfirmation.error('Cannot add default folders. Please try again later.');
            Ext.getBody().unmask();
        }
    });
}

function selectAllInTree(tree) {
    if (tree) {
        tree.expandAll();
        tree.getRootNode().cascade(function (n) {
            var ui = n.getUI();
            ui.toggleCheck(true);
        });
    }
}

function addFolder(tree, booIsRoot, member_id) {
    var folder_id = booIsRoot ? '' : getFocusedFolderId(tree);

    var text_field = new Ext.form.TextField({
        labelStyle: 'padding-top: 12px; width: 120px',
        fieldLabel: _('New folder name'),
        width: 260,
        enableKeyEvents: true,
        listeners: {
            keyup: function (field, e) {
                if (e.getKey() == Ext.EventObject.ENTER) {
                    Ext.getCmp('add-folder-button').handler.call(Ext.getCmp('add-folder-button').scope);
                }
            }
        }
    });

    var win = new Ext.Window({
        title: 'New Folder',
        layout: 'form',
        modal: true,
        autoWidth: true,
        autoHeight: true,
        resizable: false,
        items: {
            xtype: 'form',
            bodyStyle: 'padding:5px',
            labelWidth: 120,
            items: text_field
        },

        listeners: {
            show: function() {
                setTimeout(function (){
                    text_field.focus();
                }, 50);
            }
        },

        buttons: [
            {
                text: _('Cancel'),
                handler: function () {
                    win.close();
                }
            }, {
                id: 'add-folder-button',
                cls: 'orange-btn',
                text: _('OK'),
                handler: function () {
                    var folderName = trim(text_field.getValue());

                    //validate folder name
                    if (!isValidFileName(folderName)) {
                        Ext.simpleConfirmation.error('Folder name can\'t contain next characters: \\ / : * ? " < > |');
                        return false;
                    }

                    win.getEl().mask('Please wait...');
                    Ext.Ajax.request({
                        url: topBaseUrl + '/' + tree.destination + '/index/add-folder',
                        params: {
                            parent_id: folder_id || '',
                            member_id: member_id,
                            name: Ext.encode(folderName),
                            destination: tree.destination
                        },

                        success: function (result) {
                            result = Ext.decode(result.responseText);

                            win.getEl().unmask();

                            if (result.success) {
                                // Automatically expand the parent folder if it wasn't expanded yet
                                var node = getFocusedNode(tree);
                                if (node) {
                                    if (!isFolder(node)) {
                                        node = node.parentNode;
                                    }

                                    if (!node.isExpanded()) {
                                        node.expand(false, false);
                                    }
                                }

                                reload_keep_expanded(tree);
                                win.close();

                                Ext.simpleConfirmation.msg(_('Success'), result.message, 3000);
                            } else {
                                Ext.simpleConfirmation.error(result.message);
                            }
                        },

                        failure: function () {
                            Ext.simpleConfirmation.error('Can\'t add new Folder. Please try again later.');
                            win.close();
                        }
                    });
                    return false;
                }
            }
        ]
    });

    win.show();
}

function renameFolder(tree, node, type, member_id) {
    if (node) {

        var folder_id = getNodeId(node);

        if (!isFolder(node)) {
            node = node.parentNode;
        }

        // Inner files will be moved too,
        // So close the preview for these opened files
        var arrFilesHashes = tree.getFolderFilesHashes(node);

        var text_field = new Ext.form.TextField({
            labelStyle: 'padding-top: 12px; width: 170px',
            fieldLabel: _('Please type folder name'),
            width: 260,
            value: getNodeFileName(node),
            enableKeyEvents: true,
            listeners: {
                keyup: function (field, e) {
                    if (e.getKey() == Ext.EventObject.ENTER) {
                        Ext.getCmp('rename-folder-button').handler.call(Ext.getCmp('rename-folder-button').scope);
                    }
                }
            }
        });

        var win = new Ext.Window({
            title: 'Rename folder',
            layout: 'form',
            modal: true,
            autoWidth: true,
            autoHeight: true,
            resizable: false,
            items: {
                xtype: 'form',
                bodyStyle: 'padding:5px',
                labelWidth: 170,
                items: [text_field]
            },

            listeners: {
                show: function() {
                    setTimeout(function (){
                        text_field.focus();
                    }, 50);
                }
            },

            buttons: [
                {
                    text: _('Cancel'),
                    handler: function () {
                        win.close();
                    }
                }, {
                    id: 'rename-folder-button',
                    text: _('OK'),
                    cls: 'orange-btn',
                    handler: function () {

                        //validate folder name
                        var folder_name = trim(text_field.getValue());

                        if (!isValidFileName(folder_name)) {
                            Ext.simpleConfirmation.error('Folder name can\'t contain next characters: \\ / : * ? " < > |');
                            return false;
                        }

                        //update folder name
                        win.getEl().mask(_('Renaming...'));
                        Ext.Ajax.request({
                            url: topBaseUrl + '/' + tree.destination + '/index/rename-folder',
                            params: {
                                folder_id: folder_id,
                                folder_name: Ext.encode(folder_name),
                                member_id: Ext.encode(member_id),
                                type: Ext.encode(type)
                            },

                            success: function (f) {
                                win.getEl().unmask();

                                var result = Ext.decode(f.responseText);

                                if (result.success) {
                                    reload_keep_expanded(tree);

                                    if (arrFilesHashes.length) {
                                        tree.owner.documentsPreviewPanel.closePreviews(arrFilesHashes);
                                    }

                                    win.close();
                                } else {
                                    var showMsg = empty(result.message) ? _('Folder was not renamed. Please try again later.') : result.message;
                                    Ext.simpleConfirmation.error(showMsg);
                                }
                            },

                            failure: function () {
                                win.getEl().unmask();
                                Ext.simpleConfirmation.error(_('Can\'t rename folder. Please try again later.'));
                            }
                        });

                        return false;
                    }
                }
            ]
        });

        win.show();
    }
}

function initAsyncTree(booAutoSelect) {
    return new Ext.tree.AsyncTreeNode({

        allowDrop: false,
        allowDrag: false,
        expanded: true,

        allowRW: true,
        allowEdit: true,
        allowDeleteFolder: false,

        listeners: {
            load: function (node) {
                if (booAutoSelect) {
                    setTimeout(function () {
                        if (node.firstChild) {
                            node.firstChild.select();
                            node.firstChild.fireEvent('click', node.firstChild);
                        }
                    }, 500);
                }
            }
        }
    });
}

function saveEmlFileAttachment(file_id, destination, file_name) {
    var params = {
        id: file_id,
        file_name: file_name
    };

    submit_hidden_form(topBaseUrl + '/' + destination + '/index/download-email/', params);
}

function documentSaveEmailToInbox(tree, member_id, booIsProspect) {
    var files = getSelectedFiles(tree);
    if (files && files.length > 0) {

        var ids = [];
        for (var i = 0; i < files.length; i++) {
            if (files[i].attributes.allowSaveToInbox) {
                ids.push(getNodeId(files[i]));
            }
        }

        Ext.getBody().mask('Saving...');
        Ext.Ajax.request({
            url: topBaseUrl + '/' + tree.destination + '/index/save-to-inbox',
            params: {
                ids: Ext.encode(ids),
                member_id: member_id,
                is_prospect: Ext.encode(booIsProspect ? true : false)
            },
            success: function (result) {

                result = Ext.decode(result.responseText);
                if (result.success) {
                    // Refresh email tab if it exists
                    var emailTab = Ext.getCmp('email-tab');
                    if (emailTab) {
                        emailTab.booRefreshOnActivate = true;
                    }

                    Ext.simpleConfirmation.msg('Info', result.message);
                } else {
                    Ext.simpleConfirmation.error(result.message);
                }

                Ext.getBody().unmask();
            },
            failure: function () {
                Ext.simpleConfirmation.error('Can\'t save file(s). Please try again later.');
                Ext.getBody().unmask();
            }
        });

    } else {
        Ext.simpleConfirmation.msg('Info', 'Please select at least one email file.');
    }

    return false;
}

function documentSendEmail(tree, member_id, booProspect) {
    var files = getSelectedFiles(tree);
    if (files && files.length > 0) {

        var attachments = [];
        for (var i = 0; i < files.length; i++) {
            attachments.push({
                name: getNodeFileName(files[i]),
                link: '#',
                onclick: 'submit_hidden_form(\'' + topBaseUrl + '/' + tree.destination + '/index/get-file/\', {id: \'' + getNodeId(files[i]) + '\', member_id: ' + member_id + '}); return false;',
                size: files[i].attributes.filesize,
                libreoffice_supported: files[i].attributes.libreoffice_supported,
                file_id: getNodeId(files[i])});
        }

        show_email_dialog({
            member_id: member_id,
            attach: attachments,
            booProspect: booProspect,
            save_to_prospect: booProspect,
            booDontPreselectTemplate: false
        });
    } else {
        Ext.simpleConfirmation.msg('Info', 'Please select at least one file to email it.');
    }

    return false;
}

function isValidFileName(name) {
    return !empty(name) && name.length > 0 && name.search(/(\\|\/|\*|\?|"|<|>|\|)/) === -1;
}

function isValidUrl(str) {
    var url;

    try {
        url = new URL(str);
    } catch (_) {
        return false;
    }

    return url.protocol === "http:" || url.protocol === "https:";
}

function printEmlFile(obj) {
    var selector = $(obj).parent().parent();
    var content  = $('.eml-content', selector).html();
    var subject  = $('.eml-filename', selector).html();

    print(content, subject);
}

function forwardEmlFile(obj) {
    var selector = $(obj).parent().parent();
    var oMail    = {
        mail_id:      0,
        mail_from:    $('.eml-label-from', selector).html(),
        mail_to:      $('.eml-label-to', selector).html(),
        mail_cc:      $('.eml-label-cc', selector).html(),
        mail_bcc:     $('.eml-label-bcc', selector).html(),
        mail_body:    $('.eml-content', selector).html(),
        mail_date:    $('.eml-label-date', selector).html(),
        mail_subject: $('.eml-label-subject', selector).html(),
        attachments: []
    };

    var oDialog = new MailCreateDialog();
    oDialog.createForwardDialog(oMail);
}

function replyEmlFile(obj, booReplyAll) {
    var selector = $(obj).parent().parent();
    var oMail    = {
        mail_id:      0,
        mail_from:    $('.eml-label-from', selector).html(),
        mail_to:      $('.eml-label-to', selector).html(),
        mail_cc:      $('.eml-label-cc', selector).html(),
        mail_bcc:     $('.eml-label-bcc', selector).html(),
        mail_body:    $('.eml-content', selector).html(),
        mail_date:    $('.eml-label-date', selector).html(),
        mail_subject: $('.eml-label-subject', selector).html()
    };

    var oDialog = new MailCreateDialog();
    oDialog.createReplyDialog(booReplyAll, oMail);
}

function documentPrintEmail(tree, member_id) {
    var file = getFocusedNode(tree);
    if (file) {
        Ext.getBody().mask('Prepare to print. Please wait...');
        Ext.Ajax.request({
            url: topBaseUrl + '/' + tree.destination + '/index/print-email',
            params: {
                file_id: getNodeId(file),
                member_id: member_id
            },
            success: function (result) {
                result = Ext.decode(result.responseText);
                if (result.success) {
                    print(result.content, file.attributes.filename);
                }
                Ext.getBody().unmask();
            },
            failure: function () {
                Ext.simpleConfirmation.error('Can\'t print file. Please try again later.');
                Ext.getBody().unmask();
            }
        });
    }
}

function fixDocumentsParentTabHeight(caseId) {
    var docsTab = Ext.getCmp('ctab-client-' + caseId + '-sub-tab-documents');
    if (docsTab) {
        var applicantTab = Ext.getCmp(docsTab.panelType + '-tab-' + docsTab.applicantId + '-' + caseId);
        if (applicantTab) {
            applicantTab.items.get(0).owner.fixParentPanelHeight();
        }
    }
}