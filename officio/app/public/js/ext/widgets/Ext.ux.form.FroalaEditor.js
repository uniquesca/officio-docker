Ext.ns('Ext.ux.form');

/**
 * @class Ext.ux.form.FroalaEditor
 * @extends Ext.form.TextArea
 * @xtype froalaeditor
 *
 */
Ext.ux.form.FroalaEditor = Ext.extend(Ext.form.TextArea, {
    /*
     * Internal. FroalaEditor#setData is asynchronous; we need to call next #setData after first has been finished; otherwise
     * next value will be loosed.
     */
    _setDataInProgress: false,

    /*
     * Internal. See #_setDataInProgress.
     */
    _delayedSetData: [],

    /**
     * @property {Boolean} _froalaEditor
     * Identifies this class and its subclasses.
     * @readonly
     */
    _froalaEditor: null,

    /**
     * @property {Boolean} _isReady
     * Flags whether the Froala editor instance has been initialized. Initialization
     * happens automatically when the component is created, but takes several milliseconds.
     * Upon initialization, the instanceReady event is fired.
     * @readonly
     */
    _isReady: false,

    /**
     * @property {Int} _heightDifference
     * A difference in pixels for the height of the editor
     */
    _heightDifference: 0,

    /**
     * @property {Int} _initialWidthDifference
     * A difference in pixels for the width of the editor (used during initialization)
     */
    _initialWidthDifference: 32,

    /**
     * @property {Int} _widthDifference
     * A difference in pixels for the width of the editor (used after changing the width later)
     */
    _widthDifference: 0,

    constructor: function (config) {
        this.config = config || {};
        config.listeners = config.listeners || {};
        Ext.applyIf(config.listeners, {
            beforedestroy: this.destroyInstance.createDelegate(this),
            scope: this
        });
        Ext.ux.form.FroalaEditor.superclass.constructor.call(this, config);
    },

    onRender: function (ct, position) {
        var self = this;
        if (!this.el) {
            this.defaultAutoCreate = {
                tag: 'textarea',
                autocomplete: 'off'
            };
        }
        Ext.ux.form.FroalaEditor.superclass.onRender.call(this, ct, position);

        this.config.FroalaConfig = this.config.FroalaConfig || {};

        self._heightDifference = self.config['heightDifference'] ? self.config['heightDifference'] : 0;
        self._initialWidthDifference = self.config['initialWidthDifference'] ? self.config['initialWidthDifference'] : self._initialWidthDifference;
        self._widthDifference = self.config['widthDifference'] ? self.config['widthDifference'] : self._widthDifference;


        var defaultConfig = {
            iframe: true,
            iframeStyleFiles: [
                '/assets/plugins/bootstrap/dist/css/bootstrap.min.css',
                '/styles/froala_reset.css'
            ],
            zIndex: 10000,
            attribution: false,
            useClasses: booUseClasses,
            width: self.config['width'] ? self.config['width'] - self._initialWidthDifference : null,
            height: self.config['height'] - self._heightDifference,
            placeholderText: self.config['emptyText'],
            editorClass: self.config['class'],
            linkAlwaysBlank: true,

            imageInsertButtons: ['imageBack', '|', 'imageByURL'],
            imageEditButtons: ['imageReplace', 'imageAlign', 'imageCaption', 'imageRemove', '|', 'imageLink', 'linkOpen', 'linkEdit', 'linkRemove', '-', 'imageDisplay', 'imageStyle', 'imageAlt', 'imageSize'],
            videoInsertButtons: ['videoBack', '|', 'videoByURL', 'videoEmbed'],
            imageResizeWithPercent: true,
            imageDefaultWidth: 0, // auto
            htmlUntouched: true,

            events: {
                'contentChanged': function () {
                    self.fireEvent('change', self, self.getValue());
                },

                'focus': function () {
                    self.fireEvent('focus', self);
                },

                'blur': function () {
                    self.fireEvent('blur', self);
                }
            }
        };

        var booUseClasses;
        if (self.config['booUseSimpleFroalaPanel']) {
            defaultConfig.useClasses = true;
            defaultConfig.pluginsEnabled = ['align', 'colors', 'draggable', 'embedly', 'fontFamily', 'fontSize', 'lineHeight', 'link', 'lists', 'paragraphFormat', 'paragraphStyle'];

            if (self.config['booBasicEdition']) {
                defaultConfig.toolbarButtons = {
                    'moreText': {
                        'buttons': ['bold', 'italic', 'underline', 'inlineClass', 'inlineStyle', 'clearFormatting'],
                        'buttonsVisible': 6
                    },
                    'moreMisc': {
                        'buttons': ['undo', 'redo'],
                        'align': 'right',
                        'buttonsVisible': 2
                    }
                };
            } else {
                defaultConfig.toolbarButtons = {
                    'moreText': {
                        'buttons': ['bold', 'italic', 'underline', 'fontFamily', 'fontSize', 'textColor', 'strikeThrough', 'subscript', 'superscript', 'backgroundColor', 'inlineClass', 'inlineStyle', 'clearFormatting'],
                        'buttonsVisible': 6
                    },
                    'moreParagraph': {
                        'buttons': ['alignLeft', 'alignCenter', 'formatOLSimple', 'formatUL', 'paragraphFormat'],
                        'buttonsVisible': 5
                    },
                    'moreRich': {
                        'buttons': ['insertLink']
                    },
                    'moreMisc': {
                        'buttons': ['undo', 'redo'],
                        'align': 'right',
                        'buttonsVisible': 2
                    }
                };
            }
        } else {
            defaultConfig.useClasses = false;
            defaultConfig.pluginsEnabled = ['align', 'colors', 'draggable', 'embedly', 'emoticons', 'fontAwesome', 'fontFamily', 'fontSize', 'fullscreen', 'image', 'lineHeight', 'link', 'lists', 'paragraphFormat', 'paragraphStyle', 'print', 'quote', 'table', 'url', 'video', 'specialCharacters', 'wordPaste', 'help', 'codeView'];


            // Custom PDF generation button
            FroalaEditor.DefineIcon('generatePdfCustom', {NAME: 'info', SVG_KEY: 'pdfExport'});
            FroalaEditor.RegisterCommand('generatePdfCustom', {
                title: 'Generate PDF',
                focus: false,
                undo: false,
                refreshAfterCallback: false,
                callback: function () {
                    html2pdf()
                        .set({margin: [10, 20]})
                        .from(self._froalaEditor.html.get())
                        .save();
                }
            });

            defaultConfig.toolbarButtons = {
                'moreText': {
                    'buttons': ['bold', 'italic', 'underline', 'fontFamily', 'fontSize', 'textColor', 'strikeThrough', 'subscript', 'superscript', 'backgroundColor', 'inlineClass', 'inlineStyle', 'clearFormatting'],
                    'buttonsVisible': 6
                },
                'moreParagraph': {
                    'buttons': ['alignLeft', 'alignCenter', 'formatOLSimple', 'formatUL', 'paragraphFormat', 'alignRight', 'alignJustify', 'formatOL', 'paragraphStyle', 'lineHeight', 'outdent', 'indent', 'quote'],
                    'buttonsVisible': 5
                },
                'moreRich': {
                    'buttons': ['insertLink', 'insertImage', 'insertTable', 'insertVideo', 'emoticons', 'fontAwesome', 'specialCharacters', 'embedly', 'insertFile', 'insertHR']
                },
                'moreMisc': {
                    // Please note that a custom button is used here: generatePdfCustom
                    'buttons': ['undo', 'redo', 'print', 'generatePdfCustom', 'selectAll', 'html', 'help'],
                    'align': 'right',
                    'buttonsVisible': 2
                }
            }
        }

        // Use the license key if is provided
        if (typeof FROALA_SETTINGS !== 'undefined' && typeof FROALA_SETTINGS['key'] !== 'undefined') {
            defaultConfig['key'] = FROALA_SETTINGS['key'];
        }

        // Enable images uploading only in specific situations
        if (self.config['booAllowImagesUploading']) {
            defaultConfig['imageInsertButtons'] = ['imageBack', '|', 'imageUpload', 'imageByURL'];
            defaultConfig['imageUploadParam'] = 'upload';
            defaultConfig['imageUploadURL'] = topBaseUrl + '/documents/manager/upload-image?';
            defaultConfig['imageUploadMethod'] = 'POST';

            if (typeof FROALA_SETTINGS !== 'undefined' && typeof FROALA_SETTINGS['supported_formats'] !== 'undefined') {
                defaultConfig['imageAllowedTypes'] = FROALA_SETTINGS['supported_formats'];
            } else {
                defaultConfig['imageAllowedTypes'] = ['jpeg', 'jpg', 'png', 'gif'];
            }

            if (typeof FROALA_SETTINGS !== 'undefined' && typeof FROALA_SETTINGS['image_max_size'] !== 'undefined') {
                defaultConfig['imageMaxSize'] = FROALA_SETTINGS['image_max_size'];
            } else {
                defaultConfig['imageMaxSize'] = 5242880;
            }

            defaultConfig['events']['image.uploaded'] = function (response) {
                self._froalaEditor.image.get().addClass('officio-uploaded');
            }

            defaultConfig['events']['image.error'] = function (error, response) {
                var errorMessage = error.message;
                try {
                    var resultDecoded = Ext.decode(response);
                    errorMessage = resultDecoded.error.message;
                } catch (e) {
                }

                Ext.simpleConfirmation.error(errorMessage);
            }

            defaultConfig['events']['image.removed'] = function ($img) {
                // Don't try to send a request if this is not "officio image"
                if (!$img.hasClass('officio-uploaded')) {
                    return;
                }

                // Show the image, remove on success only
                self._froalaEditor.commands.undo();

                var oldMinWidth = Ext.Msg.minWidth;
                Ext.Msg.minWidth = 650;
                Ext.Msg.confirm(_('Please confirm'), _('Are you sure you want to delete the highlighted section including the image(s)? You cannot undo the deleted image.'), function (btn) {
                    if (btn === 'yes') {
                        Ext.Ajax.request({
                            url: topBaseUrl + '/documents/manager/delete-image',
                            params: {
                                img: Ext.encode($img.attr('src'))
                            },

                            success: function (res) {
                                Ext.Msg.minWidth = oldMinWidth;
                                Ext.getBody().unmask();

                                var result = Ext.decode(res.responseText);
                                if (!result.success) {
                                    Ext.simpleConfirmation.error(result.message);
                                } else {
                                    self._froalaEditor.commands.redo();
                                    self._froalaEditor.undo.reset();
                                }
                            },

                            failure: function () {
                                Ext.Msg.minWidth = oldMinWidth;
                                Ext.simpleConfirmation.error(_('The image was not deleted.'));
                            }
                        });
                    }
                });
            }
        }

        if (defaultConfig.pluginsEnabled.includes('fontFamily')) {
            // The list of default fonts - the same as Froala has by default
            var oDefaultFonts = {
                'Arial,Helvetica,sans-serif': 'Arial',
                'Georgia,serif': 'Georgia',
                'Impact,Charcoal,sans-serif': 'Impact',
                'Tahoma,Geneva,sans-serif': 'Tahoma',
                "'Times New Roman',Times,serif": 'Times New Roman',
                'Verdana,Geneva,sans-serif': 'Verdana'
            };

            // Add Calibri if it is installed on the system
            if (self.isFontAvailable('Calibri')) {
                oDefaultFonts['Calibri,sans-serif'] = 'Calibri';
            }
            defaultConfig['fontFamily'] = oDefaultFonts;
        }

        this.config.FroalaConfig = Ext.apply(defaultConfig, this.config.FroalaConfig);

        self._froalaEditor = new FroalaEditor(
            '#' + this.id,
            this.config.FroalaConfig,
            function () {
                self._isReady = true;
                self._froalaEditor.html.set(self.config['value']);

                if (self.config['disabled']) {
                    self.setReadOnly(true);
                }

                self.fireEvent('instanceReady', self, self._froalaEditor);
            }
        );
    },

    syncIframeSize: function () {
        this._withEditor(function (editor) {
            editor.size.refresh();
            editor.size.syncIframe();
        });
    },

    isFontAvailable: function (font) {
        // Detect if a specific font is installed on the system
        // Note: cannot use document.fonts.check as it is not fully supported yet (April 2023)
        var getWidth = function (fontFamily) {
            var container = document.createElement('span');
            container.innerHTML = Array(100).join('wi');
            container.style.cssText = [
                'position:absolute',
                'width:auto',
                'font-size:128px',
                'left:-99999px'
            ].join(' !important;');

            container.style.fontFamily = fontFamily;

            document.body.appendChild(container);
            var width = container.clientWidth;
            document.body.removeChild(container);

            return width;
        };

        return getWidth('monospace') !== getWidth(font + ',monospace') || getWidth('sans-serif') !== getWidth(font + ',sans-serif') || getWidth('serif') !== getWidth(font + ',serif');
    },

    onResize: function (width, height) {
        var self = this;
        Ext.ux.form.FroalaEditor.superclass.onResize.call(self, width, height);

        this._withEditor(function (editor) {
            editor.opts.width = width + 10 - self._widthDifference;
            editor.opts.height = height - self._heightDifference;
            editor.size.refresh();
            editor.size.syncIframe();
        });
    },

    afterRender: function () {
        Ext.ux.form.FroalaEditor.superclass.afterRender.call(this);
    },

    setValue: function (value) {
        this._handleAsyncSetValue(value);

        Ext.ux.form.FroalaEditor.superclass.setValue.apply(this, arguments);
    },

    _handleAsyncSetValue: function (value) {
        var self = this;

        if (this._setDataInProgress) {
            this._delayedSetData.push(value);
            return;
        } else {
            this._delayedSetData = [];
        }
        this._setDataInProgress = true;

        var editorAvailable = this._withEditor(function (editor) {
            editor.html.set(value);
            self._onAfterSetData();
        });

        if (!editorAvailable) {
            self._onAfterSetData.defer(1, self); // call it async just like it would be done in the 'normal' #setData flow
        }
    },

    _onAfterSetData: function () {
        this._setDataInProgress = false;
        if (this._delayedSetData.length > 0) {
            var delayedSetDataCopy = this._delayedSetData.slice(0);
            this._delayedSetData = [];
            for (var i = 0; i < delayedSetDataCopy.length; i++) {
                var delayedData = delayedSetDataCopy[i];
                delayedSetDataCopy.splice(i, 1);

                this._handleAsyncSetValue(delayedData);
            }
        }
    },

    getValue: function () {
        return this.getRawValue();
    },

    getRawValue: function () {
        if (this._froalaEditor && this._isReady) {
            return this._froalaEditor.html.get();
        } else {
            return Ext.ux.form.FroalaEditor.superclass.getValue.call(this);
        }
    },

    isValid: function () {
        return true;
    },

    validate: function () {
        return true;
    },

    validateValue: function () {
        return true;
    },

    destroyInstance: function () {
        var self = this;
        this._withEditor(function (editor) {
            editor.destroy();
            self._froalaEditor = null;
            self._isReady = false;
        });
    },

    insertAtCursor: function (value) {
        this._withEditor(function (editor) {
            editor.html.insert(value, false);
            editor.size.refresh();
            editor.size.syncIframe();
        });
    },

    injectStyle: function (strStyle) {
        this._withEditor(function (editor) {
            editor.core.injectStyle(strStyle);
        });
    },

    setReadOnly: function (booReadOnly) {
        this._withEditor(function (editor) {
            if (booReadOnly) {
                editor.edit.off();
                editor.toolbar.disable();
            } else {
                editor.edit.on();
                editor.toolbar.enable();
            }
        });
    },

    /**
     * Calls @param fn on FroalaEditor when FroalaEditor is available. Otherwise returns false.
     */
    _withEditor: function (fn) {
        if (this._froalaEditor && this._isReady) {
            fn.call(this, this._froalaEditor);
            return true;
        } else {
            return false;
        }
    }
});

Ext.reg('froalaeditor', Ext.ux.form.FroalaEditor);