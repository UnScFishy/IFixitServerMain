<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: google/firestore/admin/v1/database.proto

namespace Google\Cloud\Firestore\Admin\V1\Database;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Information about the provenance of this database.
 *
 * Generated from protobuf message <code>google.firestore.admin.v1.Database.SourceInfo</code>
 */
class SourceInfo extends \Google\Protobuf\Internal\Message
{
    /**
     * The associated long-running operation. This field may not be set after
     * the operation has completed. Format:
     * `projects/{project}/databases/{database}/operations/{operation}`.
     *
     * Generated from protobuf field <code>string operation = 3 [(.google.api.resource_reference) = {</code>
     */
    private $operation = '';
    protected $source;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \Google\Cloud\Firestore\Admin\V1\Database\SourceInfo\BackupSource $backup
     *           If set, this database was restored from the specified backup (or a
     *           snapshot thereof).
     *     @type string $operation
     *           The associated long-running operation. This field may not be set after
     *           the operation has completed. Format:
     *           `projects/{project}/databases/{database}/operations/{operation}`.
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Google\Firestore\Admin\V1\Database::initOnce();
        parent::__construct($data);
    }

    /**
     * If set, this database was restored from the specified backup (or a
     * snapshot thereof).
     *
     * Generated from protobuf field <code>.google.firestore.admin.v1.Database.SourceInfo.BackupSource backup = 1;</code>
     * @return \Google\Cloud\Firestore\Admin\V1\Database\SourceInfo\BackupSource|null
     */
    public function getBackup()
    {
        return $this->readOneof(1);
    }

    public function hasBackup()
    {
        return $this->hasOneof(1);
    }

    /**
     * If set, this database was restored from the specified backup (or a
     * snapshot thereof).
     *
     * Generated from protobuf field <code>.google.firestore.admin.v1.Database.SourceInfo.BackupSource backup = 1;</code>
     * @param \Google\Cloud\Firestore\Admin\V1\Database\SourceInfo\BackupSource $var
     * @return $this
     */
    public function setBackup($var)
    {
        GPBUtil::checkMessage($var, \Google\Cloud\Firestore\Admin\V1\Database\SourceInfo\BackupSource::class);
        $this->writeOneof(1, $var);

        return $this;
    }

    /**
     * The associated long-running operation. This field may not be set after
     * the operation has completed. Format:
     * `projects/{project}/databases/{database}/operations/{operation}`.
     *
     * Generated from protobuf field <code>string operation = 3 [(.google.api.resource_reference) = {</code>
     * @return string
     */
    public function getOperation()
    {
        return $this->operation;
    }

    /**
     * The associated long-running operation. This field may not be set after
     * the operation has completed. Format:
     * `projects/{project}/databases/{database}/operations/{operation}`.
     *
     * Generated from protobuf field <code>string operation = 3 [(.google.api.resource_reference) = {</code>
     * @param string $var
     * @return $this
     */
    public function setOperation($var)
    {
        GPBUtil::checkString($var, True);
        $this->operation = $var;

        return $this;
    }

    /**
     * @return string
     */
    public function getSource()
    {
        return $this->whichOneof("source");
    }

}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(SourceInfo::class, \Google\Cloud\Firestore\Admin\V1\Database_SourceInfo::class);

