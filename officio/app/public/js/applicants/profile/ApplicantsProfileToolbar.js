var ApplicantsProfileToolbar = function (config, owner) {
    var thisToolbar = this;
    Ext.apply(this, config);
    this.owner = owner;
    this.viewOtherCasesBtnId = Ext.id();

    var booNewApplicant = empty(this.applicantId);
    var booNewCase = empty(thisToolbar.caseId) || empty(thisToolbar.caseType);
    var booCanAddNewClient = config.memberType == 'client' ? this.hasAccessTo('employer', 'add') && this.hasAccessTo('individual', 'add') && this.hasAccessTo('case', 'add') : this.hasAccessTo(config.memberType, 'add');
    var changeFieldsOptions = config.panelType === 'contacts' ? arrApplicantsSettings['access']['contact']['queue']['change'] : arrApplicantsSettings['access']['queue']['change'];

    // Show the button if we opened the case + there is access to the Time Tracker
    var booShowButton = typeof (arrTimeTrackerSettings) != 'undefined' && arrTimeTrackerSettings.access.has('show-popup') && arrTimeTrackerSettings.tracker_enable == 'Y' && !booNewCase;
    this.btnStartStopTracker = new Ext.Button({
        text: '',
        tooltip: _('Click to start/stop the timer.'),
        tooltipType: 'title',
        cls: 'btn-time-tracker',
        hidden: !booShowButton,
        scope: this,
        width: 120,
        handler: this.startStopTracker.createDelegate(this)
    });

    ApplicantsProfileToolbar.superclass.constructor.call(this, {
        height: 43,
        items: [
            {
                text: booNewApplicant && config.memberType == 'client' ? _('Save & Add a New Case') + ' <i class="las la-arrow-right" style="vertical-align: bottom"></i>' : '<i class="lar la-save"></i>' + _('Save'),
                tooltip: _('Save profile.'),
                cls: 'orange-btn',
                uniqueFieldId: 'btn-profile-save',
                hidden: booNewApplicant ? !booCanAddNewClient : !this.hasAccessTo(config.memberType, 'edit'),
                handler: thisToolbar.owner.saveApplicantDetails.createDelegate(thisToolbar.owner)
            }, {
                text: '<i class="lar la-envelope"></i>' + _('Email'),
                tooltip: _('Send email.'),
                hidden: booNewApplicant || !allowedPages.has('email'),
                handler: function () {
                    var options = {
                        member_id: empty(thisToolbar.caseId) ? 0 : thisToolbar.caseId,
                        parentMemberId: thisToolbar.applicantId,
                        booContact: config.memberType == 'contact',
                        booHideSendAndSaveProspect: true,
                        booNewEmail: true,
                        booProspect: false
                    };

                    show_email_dialog(options);
                }
            }, {
                text: '<i class="las la-print"></i>' + _('Print'),
                tooltip: _('Print profile.'),
                hidden: booNewApplicant,
                handler: function () {
                    thisToolbar.owner.printApplicantDetails();
                }
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete'),
                tooltip: config.memberType === 'case' ? _('Delete Case.') : _('Delete Profile.'),
                uniqueFieldId: 'btn-profile-delete',
                hidden: booNewApplicant || (config.memberType === 'case' && empty(thisToolbar.caseId)) || !this.hasAccessTo(config.memberType, 'delete'),
                handler: function () {
                    thisToolbar.owner.deleteApplicant();
                }
            }, {
                text: '', // The label will be generated after we'll load case's details
                uniqueFieldId: 'btn-link-case-to-lmia-case',
                hidden: true, // This button will be showed after we'll load case's details
                handler: function () {
                    thisToolbar.owner.linkCaseToLMIACase();
                }
            }, {
                text: '', // The label will be generated after we'll load case's details
                uniqueFieldId: 'btn-unlink-case-from-lmia-case-or-employer',
                hidden: true, // This button will be showed after we'll load case's details
                handler: function () {
                    thisToolbar.owner.unlinkCaseFromLMIACaseOrEmployer();
                }
            }, {
                text: '<i class="las la-link"></i>' + _('Link to an Employer'),
                tooltip: _('Link to an Employer'),
                uniqueFieldId: 'btn-link-case-to-employer',
                hidden: true, // This button will be showed after we'll load case's details
                handler: function () {
                    thisToolbar.owner.linkCaseToEmployer();
                }
            }, {
                text: '<i class="las la-external-link-square-alt"></i>' + _('Push to ') + arrApplicantsSettings.office_label,
                tooltip: _('Forward this client to another ') + arrApplicantsSettings.office_label + '.',
                uniqueFieldId: 'btn-applicant-push-to-office',
                hidden: booNewApplicant || booNewCase || !changeFieldsOptions['push_to_queue'],
                scope: this,
                handler: function () {
                    var arrSelectedClientIds = [];
                    if (!empty(thisToolbar.caseId)) {
                        arrSelectedClientIds.push(thisToolbar.caseId);
                    } else {
                        arrSelectedClientIds.push(thisToolbar.applicantId);
                    }

                    Ext.getBody().mask('Loading...');
                    Ext.Ajax.request({
                        url: topBaseUrl + '/applicants/queue/push-to-queue',
                        params: {
                            booLoadSavedOffices: true,
                            arrClientIds: Ext.encode(arrSelectedClientIds)
                        },

                        success: function (f) {
                            var resultData = Ext.decode(f.responseText);
                            Ext.getBody().unmask();

                            if (resultData.success) {
                                var wnd = new ApplicantsQueueApplyChangesWindow({
                                    strFieldType: 'push_to_queue',
                                    arrSelectedClientIds: arrSelectedClientIds,
                                    strSelectedOffices: resultData.arrSelectedOffices.join(','),
                                    onSuccessUpdate: function (selectedOption, arrCheckedCheckboxesLabels) {
                                        var msg = String.format(
                                            '{0} was successfully pushed to {1} {2}',
                                            thisToolbar.owner.getCurrentApplicantTabName(),
                                            arrCheckedCheckboxesLabels.join(', '),
                                            arrCheckedCheckboxesLabels.length > 1 ? arrApplicantsSettings.office_label + 's' : arrApplicantsSettings.office_label
                                        );
                                        Ext.simpleConfirmation.msg(_('Success'), msg, 1500);

                                        var officeField = thisToolbar.owner.find('fieldUniqueName', 'office');
                                        if (officeField.length) {
                                            thisToolbar.owner.updateOffices([officeField[0]['name']], [selectedOption], true);
                                        }

                                        thisToolbar.refreshOnSuccess.createDelegate(this);
                                    }
                                }, this);
                                wnd.show();
                                wnd.center();
                            } else {
                                Ext.getBody().unmask();
                                Ext.simpleConfirmation.error(resultData.message);
                            }
                        },

                        failure: function () {
                            Ext.simpleConfirmation.error('Error happened. Please try again later.');
                            Ext.getBody().unmask();
                        }
                    });
                }
            }, {
                text: 'VEVO Check',
                iconCls: 'icon-applicant-vevo',
                uniqueFieldId: 'btn-vevo-check',
                hidden: true,
                scope: this,
                handler: function () {
                    thisToolbar.getVevoCountrySuggestions();
                }
            }, {
                text: _('Submit to ') + current_member_company_name,
                tooltip: _('Submit currently selected Client to ') + current_member_company_name + '.',
                iconCls: 'icon-applicant-submit-to-government',
                uniqueFieldId: 'btn-submit-to-government',
                cls: 'orange-btn',
                width: 130,
                scope: this,
                hidden: booNewApplicant || booNewCase || !arrApplicantsSettings.access.submit_to_government,

                handler: function () {
                    var clientId = !empty(thisToolbar.caseId) ? thisToolbar.caseId : thisToolbar.applicantId;
                    thisToolbar.getCompanyAgentPaymentInfo(clientId, thisToolbar.applicantName);
                }
            }, {
                text: '<i class="las la-book"></i>' + _('Generate CON'),
                uniqueFieldId: 'btn-generate-con',
                scope: this,
                hidden: true,

                handler: function () {
                    thisToolbar.generateCon();
                }
            }, {
                text: '<i class="las la-book"></i>' + _('Generate Comfort Letter'),
                uniqueFieldId: 'btn-generate-pdf-letter',
                scope: this,
                hidden: true,

                handler: function () {
                    thisToolbar.generatePdfLetter();
                }
            },
            this.btnStartStopTracker,
            '->', {
                text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                hidden: !allowedPages.has('help'),
                handler: function () {
                    var contextId = config.memberType === 'contact' ? 'contacts-profile' : 'clients-profile';
                    showHelpContextMenu(this.getEl(), contextId);
                }
            }
        ]
    });

    this.on('render', this.initToolbarScroll.createDelegate(this));
    this.on('afterrender', this.initStartStopTrackerButton.createDelegate(this));
};

Ext.extend(ApplicantsProfileToolbar, Ext.Toolbar, {
    hasAccessTo: function (section, action) {
        var booHasAccess = false;
        if (typeof arrApplicantsSettings != 'undefined' && typeof arrApplicantsSettings['access'][section] != 'undefined') {
            if (Array.isArray(arrApplicantsSettings['access'][section])) {
                booHasAccess = arrApplicantsSettings['access'][section].has(action);
            } else if (arrApplicantsSettings['access'][section].hasOwnProperty(action) && arrApplicantsSettings['access'][section][action]) {
                booHasAccess = true;
            }
        }

        return booHasAccess;
    },

    initToolbarScroll: function () {
        var thisToolbar = this;

        setTimeout(function () {
            $('#' + thisToolbar.owner.id).parent().scroll(function () {
                thisToolbar.syncToolbarPosition();
            });
        }, 100);
    },

    syncToolbarPosition: function () {
        var thisToolbar = this;

        var $toolbar = $('#' + thisToolbar.getId());
        if ($toolbar.length && $toolbar.is(':visible')) {
            var scroller_object = $toolbar.parent();
            var scrollerEl = $('#' + thisToolbar.owner.id).parent();

            if (scrollerEl.scrollTop() <= 0) {
                scroller_object.css({
                    position: "absolute",
                    top: "0px",
                    width: $toolbar.parents('table').width() + 'px',
                    'padding-top': '0px'
                });
            } else {
                scroller_object.css({
                    position: "absolute",
                    top: (scrollerEl.scrollTop() - 20) + "px",
                    'padding-top': '20px'
                });
            }
        }
    },

    toggleThisToolbar: function (booShow) {
        this.setVisible(booShow);
        if (booShow) {
            this.syncToolbarPosition();
        }
    },

    refreshOnSuccess: function () {
        // Reload left panel(s)
        this.owner.owner.owner.refreshClientsList(this.owner.panelType, this.applicantId, this.caseId, true);
    },

    getCompanyAgentPaymentInfo: function (clientId, applicantName) {
        var thisToolbar = this;

        Ext.getBody().mask('Loading payments...');
        Ext.Ajax.request({
            url: topBaseUrl + '/applicants/index/get-company-agent-payment-info',
            params: {
                clientId: clientId
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                Ext.getBody().unmask();

                if (resultData.success) {
                    var wnd;

                    if (!resultData.booPayed) {
                        wnd = new ApplicantsProfilePaymentDialog({
                            clientId: clientId,
                            applicantName: applicantName,
                            currency: resultData.currency,
                            systemAccessFee: resultData.systemAccessFee
                        }, thisToolbar);

                    } else {
                        wnd = new ApplicantsProfileSubmitDialog({
                            clientId: clientId,
                            applicantName: applicantName
                        }, thisToolbar);
                    }

                    wnd.show();
                    wnd.center();
                } else {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.setMinWidth(600).error(resultData.message);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error('Error happened. Please try again later.');
                Ext.getBody().unmask();
            }
        });
    },

    makeReadOnly: function () {
        var arrIdsToDisable = [
            'btn-profile-save',
            'btn-profile-delete',
            'btn-link-case-to-lmia-case',
            'btn-unlink-case-from-lmia-case-or-employer',
            'btn-link-case-to-employer',
            'btn-applicant-push-to-office',
            'btn-vevo-check',
            'btn-submit-to-government',
            'btn-generate-con'
        ];

        for (var i = 0; i < arrIdsToDisable.length; i++) {
            var arrButtons = this.find('uniqueFieldId', arrIdsToDisable[i]);
            if (arrButtons.length) {
                arrButtons[0].setDisabled(true);
            }
        }
    },

    getVevoCountrySuggestions: function () {
        var thisToolbar = this;

        Ext.getBody().mask('Loading...');
        Ext.Ajax.request({
            url: topBaseUrl + '/applicants/profile/get-vevo-country-suggestions',
            params: {
                applicantId: thisToolbar.applicantId
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                Ext.getBody().unmask();

                if (resultData.success) {
                    var wnd = new ApplicantsProfileVevoCheckDialog({
                        clientId: !empty(thisToolbar.caseId) ? thisToolbar.caseId : 0,
                        countryFieldValue: resultData.countryFieldValue,
                        countrySuggestions: resultData.countrySuggestions,
                        booCorrectValue: resultData.booCorrectValue
                    }, thisToolbar);
                    wnd.show();
                    wnd.center();
                } else {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error('Error happened. Please try again later.');
                Ext.getBody().unmask();
            }
        });
    },

    generateCon: function () {
        var wnd = new ApplicantsProfileGenerateConDialog({
            caseId: this.caseId
        });

        wnd.show();
        wnd.center();
    },

    generatePdfLetter: function () {
        var wnd = new ApplicantsProfileGeneratePdfLetterDialog({
            caseId: this.caseId
        });

        wnd.show();
        wnd.center();
    },

    initStartStopTrackerButton: function () {
        this.btnStartStopTracker.setText(this.getTrackerButtonTitle());
    },

    getTrackerButtonTitle: function () {
        var thisToolbar = this;

        var booStarted = !empty(thisToolbar.owner.owner.timeTrackerIntervalId);
        var secondsPassed = thisToolbar.owner.owner.timeTrackerTimeSpent;
        var milliSecondsPassed = secondsPassed * 1000;

        const days = parseInt(milliSecondsPassed / (1000 * 60 * 60 * 24));
        const hours = parseInt(milliSecondsPassed / (1000 * 60 * 60) % 24);
        const minutes = parseInt(milliSecondsPassed / (1000 * 60) % 60);
        const seconds = parseInt(milliSecondsPassed / (1000) % 60);

        return String.format(
            _('Time Tracker') + '<br>{0}{1}:{2}:{3}',
            booStarted ? '<i class="las la-stop-circle"></i>' : '<i class="las la-play"></i>',
            hours < 10 ? '0' + hours : hours,
            minutes < 10 ? '0' + minutes : minutes,
            seconds < 10 ? '0' + seconds : seconds
        );
    },

    startStopTracker: function () {
        var thisToolbar = this;
        if (empty(thisToolbar.owner.owner.timeTrackerIntervalId)) {
            thisToolbar.owner.owner.timeTrackerIntervalId = setInterval(function () {
                thisToolbar.owner.owner.timeTrackerTimeSpent += 1;
                thisToolbar.updateBtnStartStopTrackerEverywhere(thisToolbar.getTrackerButtonTitle());
            }, 1000);

            // update right now!
            thisToolbar.updateBtnStartStopTrackerEverywhere(thisToolbar.getTrackerButtonTitle());
        } else {
            clearInterval(thisToolbar.owner.owner.timeTrackerIntervalId);
            thisToolbar.owner.owner.timeTrackerIntervalId = null;

            thisToolbar.owner.owner.showTimeTrackerDialog();

            // update right now!
            thisToolbar.owner.owner.timeTrackerTimeSpent = 0;
            thisToolbar.updateBtnStartStopTrackerEverywhere(thisToolbar.getTrackerButtonTitle());
        }
    },

    updateBtnStartStopTrackerEverywhere: function (btnText) {
        var arrProfileToolbars = this.owner.owner.findByType('ApplicantsProfileToolbar');
        Ext.each(arrProfileToolbars, function (oToolbar) {
            if (!empty(oToolbar.btnStartStopTracker)) {
                oToolbar.btnStartStopTracker.setText(btnText);
            }
        });
    }
});

Ext.reg('ApplicantsProfileToolbar', ApplicantsProfileToolbar);