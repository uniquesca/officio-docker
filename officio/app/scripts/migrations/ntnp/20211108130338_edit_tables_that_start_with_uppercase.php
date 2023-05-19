<?php

use Phinx\Migration\AbstractMigration;

class EditTablesThatStartWithUppercase extends AbstractMigration
{
    public function up()
    {
        $this
            ->table('FormAssigned')
            ->rename('form_assigned')
            ->renameColumn('FormAssignedId', 'form_assigned_id')
            ->renameColumn('ClientMemberId', 'client_member_id')
            ->renameColumn('FamilyMemberId', 'family_member_id')
            ->renameColumn('FormVersionId', 'form_version_id')
            ->renameColumn('UseRevision', 'use_revision')
            ->renameColumn('AssignDate', 'assign_date')
            ->renameColumn('AssignBy', 'assign_by')
            ->renameColumn('CompletedDate', 'completed_date')
            ->renameColumn('FinalizedDate', 'finalized_date')
            ->renameColumn('UpdatedBy', 'updated_by')
            ->renameColumn('Status', 'form_status')
            ->renameColumn('AssignAlias', 'assign_alias')
            ->renameColumn('LastUpdateDate', 'last_update_date')
            ->update();

        $this
            ->table('FormFolder')
            ->rename('form_folder')
            ->renameColumn('FolderId', 'folder_id')
            ->renameColumn('ParentId', 'parent_id')
            ->renameColumn('FolderName', 'folder_name')
            ->update();

        $this
            ->table('FormLanding')
            ->rename('form_landing')
            ->renameColumn('FolderId', 'folder_id')
            ->renameColumn('ParentId', 'parent_id')
            ->renameColumn('Name', 'folder_name')
            ->update();

        $this
            ->table('FormMap')
            ->rename('form_map')
            ->renameColumn('FormMapId', 'form_map_id')
            ->renameColumn('FromFamilyMemberId', 'from_family_member_id')
            ->renameColumn('FromSynFieldId', 'from_syn_field_id')
            ->renameColumn('ToFamilyMemberId', 'to_family_member_id')
            ->renameColumn('ToSynFieldId', 'to_syn_field_id')
            ->renameColumn('ToProfileFamilyMemberId', 'to_profile_family_member_id')
            ->renameColumn('ToProfileFieldId', 'to_profile_field_id')
            ->update();

        $this
            ->table('FormProcessed')
            ->rename('form_processed')
            ->update();

        $this
            ->table('FormRevision')
            ->rename('form_revision')
            ->renameColumn('FormRevisionId', 'form_revision_id')
            ->renameColumn('FormAssignedId', 'form_assigned_id')
            ->renameColumn('FormRevisionNumber', 'form_revision_number')
            ->renameColumn('UploadedBy', 'uploaded_by')
            ->renameColumn('UploadedOn', 'uploaded_on')
            ->update();

        $this
            ->table('FormSynField')
            ->rename('form_syn_field')
            ->renameColumn('SynFieldId', 'syn_field_id')
            ->renameColumn('FieldName', 'field_name')
            ->update();

        $this
            ->table('FormTemplates')
            ->rename('form_templates')
            ->update();

        $this
            ->table('FormUpload')
            ->rename('form_upload')
            ->renameColumn('FormId', 'form_id')
            ->renameColumn('FolderId', 'folder_id')
            ->update();

        $this
            ->table('FormVersion')
            ->rename('form_version')
            ->renameColumn('FormVersionId', 'form_version_id')
            ->renameColumn('FormId', 'form_id')
            ->renameColumn('FormType', 'form_type')
            ->renameColumn('VersionDate', 'version_date')
            ->renameColumn('FilePath', 'file_path')
            ->renameColumn('Size', 'size')
            ->renameColumn('UploadedDate', 'uploaded_date')
            ->renameColumn('UploadedBy', 'uploaded_by')
            ->renameColumn('FileName', 'file_name')
            ->renameColumn('Note1', 'note1')
            ->renameColumn('Note2', 'note2')
            ->update();
    }

    public function down()
    {
        $this
            ->table('form_assigned')
            ->rename('FormAssigned')
            ->renameColumn('form_assigned_id', 'FormAssignedId')
            ->renameColumn('client_member_id', 'ClientMemberId')
            ->renameColumn('family_member_id', 'FamilyMemberId')
            ->renameColumn('form_version_id', 'FormVersionId')
            ->renameColumn('use_revision', 'UseRevision')
            ->renameColumn('assign_date', 'AssignDate')
            ->renameColumn('assign_by', 'AssignBy')
            ->renameColumn('completed_date', 'CompletedDate')
            ->renameColumn('finalized_date', 'FinalizedDate')
            ->renameColumn('updated_by', 'UpdatedBy')
            ->renameColumn('form_status', 'Status')
            ->renameColumn('assign_alias', 'AssignAlias')
            ->renameColumn('last_update_date', 'LastUpdateDate')
            ->update();

        $this
            ->table('form_folder')
            ->rename('FormFolder')
            ->renameColumn('folder_id', 'FolderId')
            ->renameColumn('parent_id', 'ParentId')
            ->renameColumn('folder_name', 'FolderName')
            ->update();

        $this
            ->table('form_landing')
            ->rename('FormLanding')
            ->renameColumn('folder_id', 'FolderId')
            ->renameColumn('parent_id', 'ParentId')
            ->renameColumn('folder_name', 'Name')
            ->update();

        $this
            ->table('form_map')
            ->rename('FormMap')
            ->renameColumn('form_map_id', 'FormMapId')
            ->renameColumn('from_family_member_id', 'FromFamilyMemberId')
            ->renameColumn('from_syn_field_id', 'FromSynFieldId')
            ->renameColumn('to_family_member_id', 'ToFamilyMemberId')
            ->renameColumn('to_syn_field_id', 'ToSynFieldId')
            ->renameColumn('to_profile_family_member_id', 'ToProfileFamilyMemberId')
            ->renameColumn('to_profile_field_id', 'ToProfileFieldId')
            ->update();

        $this
            ->table('form_processed')
            ->rename('FormProcessed')
            ->update();

        $this
            ->table('form_revision')
            ->rename('FormRevision')
            ->renameColumn('form_revision_id', 'FormRevisionId')
            ->renameColumn('form_assigned_id', 'FormAssignedId')
            ->renameColumn('form_revision_number', 'FormRevisionNumber')
            ->renameColumn('uploaded_by', 'UploadedBy')
            ->renameColumn('uploaded_on', 'UploadedOn')
            ->update();

        $this
            ->table('form_syn_field')
            ->rename('FormSynField')
            ->renameColumn('syn_field_id', 'SynFieldId')
            ->renameColumn('field_name', 'FieldName')
            ->update();

        $this
            ->table('form_templates')
            ->rename('FormTemplates')
            ->update();

        $this
            ->table('form_upload')
            ->rename('FormUpload')
            ->renameColumn('form_id', 'FormId')
            ->renameColumn('folder_id', 'FolderId')
            ->update();

        $this
            ->table('form_version')
            ->rename('FormVersion')
            ->renameColumn('form_version_id', 'FormVersionId')
            ->renameColumn('form_id', 'FormId')
            ->renameColumn('form_type', 'FormType')
            ->renameColumn('version_date', 'VersionDate')
            ->renameColumn('file_path', 'FilePath')
            ->renameColumn('size', 'Size')
            ->renameColumn('uploaded_date', 'UploadedDate')
            ->renameColumn('uploaded_by', 'UploadedBy')
            ->renameColumn('file_name', 'FileName')
            ->renameColumn('note1', 'Note1')
            ->renameColumn('note2', 'Note2')
            ->update();
    }
}
