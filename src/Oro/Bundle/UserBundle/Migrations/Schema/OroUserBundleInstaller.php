<?php

namespace Oro\Bundle\UserBundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;

use Oro\Bundle\AttachmentBundle\Migration\Extension\AttachmentExtension;
use Oro\Bundle\AttachmentBundle\Migration\Extension\AttachmentExtensionAwareInterface;
use Oro\Bundle\ActivityBundle\Migration\Extension\ActivityExtension;
use Oro\Bundle\ActivityBundle\Migration\Extension\ActivityExtensionAwareInterface;

use Oro\Bundle\MigrationBundle\Migration\Installation;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

use Oro\Bundle\UserBundle\Migrations\Schema\v1_0\OroUserBundle;
use Oro\Bundle\UserBundle\Migrations\Schema\v1_2\OroUserBundle as UserAvatars;
use Oro\Bundle\UserBundle\Migrations\Schema\v1_3\OroUserBundle as UserEmailActivities;
use Oro\Bundle\UserBundle\Migrations\Schema\v1_4\AttachmentOwner;
use Oro\Bundle\UserBundle\Migrations\Schema\v1_5\SetOwnerForEmailTemplates as EmailTemplateOwner;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
class OroUserBundleInstaller implements
    Installation,
    AttachmentExtensionAwareInterface,
    ActivityExtensionAwareInterface
{
    /** @var AttachmentExtension */
    protected $attachmentExtension;

    /** @var ActivityExtension */
    protected $activityExtension;

    /**
     * {@inheritdoc}
     */
    public function getMigrationVersion()
    {
        return 'v1_5';
    }

    /**
     * {@inheritdoc}
     */
    public function setAttachmentExtension(AttachmentExtension $attachmentExtension)
    {
        $this->attachmentExtension = $attachmentExtension;
    }

    /**
     * {@inheritdoc}
     */
    public function setActivityExtension(ActivityExtension $activityExtension)
    {
        $this->activityExtension = $activityExtension;
    }

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        /** Tables generation **/
        $this->createOroUserEmailTable($schema);
        $this->createOroUserApiTable($schema);

        $this->createOroUserTable($schema);
        UserAvatars::addAvatarToUser($schema, $this->attachmentExtension);
        UserAvatars::addOwnerToOroFile($schema);
        AttachmentOwner::addOwnerToAttachment($schema);

        $this->createOroUserAccessRoleTable($schema);
        $this->createOroUserAccessGroupTable($schema);
        $this->createOroUserBusinessUnitTable($schema);
        $this->createOroUserEmailOriginTable($schema);
        $this->createOroAccessGroupTable($schema);
        $this->createOroUserAccessGroupRoleTable($schema);
        $this->createOroAccessRoleTable($schema);
        $this->createOroSessionTable($schema);
        $this->createOroUserStatusTable($schema);

        /** Foreign keys generation **/
        $this->addOroUserEmailForeignKeys($schema);
        $this->addOroUserApiForeignKeys($schema);
        $this->addOroUserForeignKeys($schema);
        $this->addOroUserAccessRoleForeignKeys($schema);
        $this->addOroUserAccessGroupForeignKeys($schema);
        $this->addOroUserBusinessUnitForeignKeys($schema);
        $this->addOroUserEmailOriginForeignKeys($schema);
        $this->addOroAccessGroupForeignKeys($schema);
        $this->addOroUserAccessGroupRoleForeignKeys($schema);
        $this->addOroAccessRoleForeignKeys($schema);
        $this->addOroUserStatusForeignKeys($schema);

        EmailTemplateOwner::addOwnerToOroEmailTemplate($schema);
        OroUserBundle::addOwnerToOroEmailAddress($schema);
        UserEmailActivities::addActivityAssociations($schema, $this->activityExtension);
    }

    /**
     * Create oro_user_email table
     *
     * @param Schema $schema
     */
    protected function createOroUserEmailTable(Schema $schema)
    {
        $table = $schema->createTable('oro_user_email');
        $table->addColumn('id', 'integer', ['precision' => 0, 'autoincrement' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => false]);
        $table->addColumn('email', 'string', ['length' => 255, 'precision' => 0]);
        $table->addIndex(['user_id'], 'IDX_8600BE16A76ED395', []);
        $table->setPrimaryKey(['id']);
    }

    /**
     * Create oro_user_api table
     *
     * @param Schema $schema
     */
    protected function createOroUserApiTable(Schema $schema)
    {
        $table = $schema->createTable('oro_user_api');
        $table->addColumn('id', 'integer', ['precision' => 0, 'autoincrement' => true]);
        $table->addColumn('user_id', 'integer', []);
        $table->addColumn('api_key', 'string', ['length' => 255, 'precision' => 0]);
        $table->addUniqueIndex(['api_key'], 'UNIQ_296B6993C912ED9D');
        $table->addUniqueIndex(['user_id'], 'UNIQ_296B6993A76ED395');
        $table->setPrimaryKey(['id']);
    }

    /**
     * Create oro_user table
     *
     * @param Schema $schema
     */
    protected function createOroUserTable(Schema $schema)
    {
        $table = $schema->createTable('oro_user');
        $table->addColumn('id', 'integer', ['precision' => 0, 'autoincrement' => true]);
        $table->addColumn('business_unit_owner_id', 'integer', ['notnull' => false]);
        $table->addColumn('status_id', 'integer', ['notnull' => false]);
        $table->addColumn('username', 'string', ['length' => 255, 'precision' => 0]);
        $table->addColumn('email', 'string', ['length' => 255, 'precision' => 0]);
        $table->addColumn('name_prefix', 'string', ['notnull' => false, 'length' => 255, 'precision' => 0]);
        $table->addColumn('first_name', 'string', ['notnull' => false, 'length' => 255, 'precision' => 0]);
        $table->addColumn('middle_name', 'string', ['notnull' => false, 'length' => 255, 'precision' => 0]);
        $table->addColumn('last_name', 'string', ['notnull' => false, 'length' => 255, 'precision' => 0]);
        $table->addColumn('name_suffix', 'string', ['notnull' => false, 'length' => 255, 'precision' => 0]);
        $table->addColumn('birthday', 'date', ['notnull' => false, 'precision' => 0]);
        $table->addColumn('enabled', 'boolean', ['precision' => 0]);
        $table->addColumn('salt', 'string', ['length' => 255, 'precision' => 0]);
        $table->addColumn('password', 'string', ['length' => 255, 'precision' => 0]);
        $table->addColumn('confirmation_token', 'string', ['notnull' => false, 'length' => 255, 'precision' => 0]);
        $table->addColumn('password_requested', 'datetime', ['notnull' => false, 'precision' => 0]);
        $table->addColumn('last_login', 'datetime', ['notnull' => false, 'precision' => 0]);
        $table->addColumn('login_count', 'integer', ['default' => '0', 'precision' => 0, 'unsigned' => true]);
        $table->addColumn('createdAt', 'datetime', ['precision' => 0]);
        $table->addColumn('updatedAt', 'datetime', ['precision' => 0]);
        $table->addUniqueIndex(['username'], 'UNIQ_F82840BCF85E0677');
        $table->addUniqueIndex(['email'], 'UNIQ_F82840BCE7927C74');
        $table->addIndex(['business_unit_owner_id'], 'IDX_F82840BC59294170', []);
        $table->addUniqueIndex(['status_id'], 'UNIQ_F82840BC6BF700BD');
        $table->setPrimaryKey(['id']);
    }

    /**
     * Create oro_user_access_role table
     *
     * @param Schema $schema
     */
    protected function createOroUserAccessRoleTable(Schema $schema)
    {
        $table = $schema->createTable('oro_user_access_role');
        $table->addColumn('user_id', 'integer', []);
        $table->addColumn('role_id', 'integer', []);
        $table->addIndex(['user_id'], 'IDX_290571BEA76ED395', []);
        $table->addIndex(['role_id'], 'IDX_290571BED60322AC', []);
        $table->setPrimaryKey(['user_id', 'role_id']);
    }

    /**
     * Create oro_user_access_group table
     *
     * @param Schema $schema
     */
    protected function createOroUserAccessGroupTable(Schema $schema)
    {
        $table = $schema->createTable('oro_user_access_group');
        $table->addColumn('user_id', 'integer', []);
        $table->addColumn('group_id', 'integer', []);
        $table->addIndex(['user_id'], 'IDX_EC003EF3A76ED395', []);
        $table->addIndex(['group_id'], 'IDX_EC003EF3FE54D947', []);
        $table->setPrimaryKey(['user_id', 'group_id']);
    }

    /**
     * Create oro_user_business_unit table
     *
     * @param Schema $schema
     */
    protected function createOroUserBusinessUnitTable(Schema $schema)
    {
        $table = $schema->createTable('oro_user_business_unit');
        $table->addColumn('user_id', 'integer', []);
        $table->addColumn('business_unit_id', 'integer', []);
        $table->addIndex(['user_id'], 'IDX_B190CE8FA76ED395', []);
        $table->addIndex(['business_unit_id'], 'IDX_B190CE8FA58ECB40', []);
        $table->setPrimaryKey(['user_id', 'business_unit_id']);
    }

    /**
     * Create oro_user_email_origin table
     *
     * @param Schema $schema
     */
    protected function createOroUserEmailOriginTable(Schema $schema)
    {
        $table = $schema->createTable('oro_user_email_origin');
        $table->addColumn('user_id', 'integer', []);
        $table->addColumn('origin_id', 'integer', []);
        $table->addIndex(['user_id'], 'IDX_CB3E838BA76ED395', []);
        $table->addIndex(['origin_id'], 'IDX_CB3E838B56A273CC', []);
        $table->setPrimaryKey(['user_id', 'origin_id']);
    }

    /**
     * Create oro_access_group table
     *
     * @param Schema $schema
     */
    protected function createOroAccessGroupTable(Schema $schema)
    {
        $table = $schema->createTable('oro_access_group');
        $table->addColumn('id', 'integer', ['precision' => 0, 'autoincrement' => true]);
        $table->addColumn('business_unit_owner_id', 'integer', ['notnull' => false]);
        $table->addColumn('name', 'string', ['length' => 30, 'precision' => 0]);
        $table->addUniqueIndex(['name'], 'UNIQ_FEF9EDB75E237E06');
        $table->addIndex(['business_unit_owner_id'], 'IDX_FEF9EDB759294170', []);
        $table->setPrimaryKey(['id']);
    }

    /**
     * Create oro_user_access_group_role table
     *
     * @param Schema $schema
     */
    protected function createOroUserAccessGroupRoleTable(Schema $schema)
    {
        $table = $schema->createTable('oro_user_access_group_role');
        $table->addColumn('group_id', 'integer', []);
        $table->addColumn('role_id', 'integer', []);
        $table->addIndex(['group_id'], 'IDX_E7E7E38EFE54D947', []);
        $table->addIndex(['role_id'], 'IDX_E7E7E38ED60322AC', []);
        $table->setPrimaryKey(['group_id', 'role_id']);
    }

    /**
     * Create oro_access_role table
     *
     * @param Schema $schema
     */
    protected function createOroAccessRoleTable(Schema $schema)
    {
        $table = $schema->createTable('oro_access_role');
        $table->addColumn('id', 'integer', ['precision' => 0, 'autoincrement' => true]);
        $table->addColumn('business_unit_owner_id', 'integer', ['notnull' => false]);
        $table->addColumn('role', 'string', ['length' => 30, 'precision' => 0]);
        $table->addColumn('label', 'string', ['length' => 30, 'precision' => 0]);
        $table->addUniqueIndex(['role'], 'UNIQ_673F65E757698A6A');
        $table->addIndex(['business_unit_owner_id'], 'IDX_673F65E759294170', []);
        $table->setPrimaryKey(['id']);
    }

    /**
     * Create oro_session table
     *
     * @param Schema $schema
     */
    protected function createOroSessionTable(Schema $schema)
    {
        $table = $schema->createTable('oro_session');
        $table->addColumn('id', 'string', ['length' => 255, 'precision' => 0]);
        $table->addColumn('sess_data', 'text', ['precision' => 0]);
        $table->addColumn('sess_time', 'integer', ['precision' => 0]);
        $table->setPrimaryKey(['id']);
    }

    /**
     * Create oro_user_status table
     *
     * @param Schema $schema
     */
    protected function createOroUserStatusTable(Schema $schema)
    {
        $table = $schema->createTable('oro_user_status');
        $table->addColumn('id', 'integer', ['precision' => 0, 'autoincrement' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => false]);
        $table->addColumn('status', 'string', ['length' => 255, 'precision' => 0]);
        $table->addColumn('created_at', 'datetime', ['precision' => 0]);
        $table->addIndex(['user_id'], 'IDX_D8DDF7AAA76ED395', []);
        $table->setPrimaryKey(['id']);
    }

    /**
     * Add oro_user_email foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroUserEmailForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_user_email');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_user'),
            ['user_id'],
            ['id'],
            []
        );
    }

    /**
     * Add oro_user_api foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroUserApiForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_user_api');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_user'),
            ['user_id'],
            ['id'],
            []
        );
    }

    /**
     * Add oro_user foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroUserForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_user');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_business_unit'),
            ['business_unit_owner_id'],
            ['id'],
            ['onDelete' => 'SET NULL']
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_user_status'),
            ['status_id'],
            ['id'],
            []
        );
    }

    /**
     * Add oro_user_access_role foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroUserAccessRoleForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_user_access_role');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_user'),
            ['user_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_access_role'),
            ['role_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
    }

    /**
     * Add oro_user_access_group foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroUserAccessGroupForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_user_access_group');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_user'),
            ['user_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_access_group'),
            ['group_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
    }

    /**
     * Add oro_user_business_unit foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroUserBusinessUnitForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_user_business_unit');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_user'),
            ['user_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_business_unit'),
            ['business_unit_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
    }

    /**
     * Add oro_user_email_origin foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroUserEmailOriginForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_user_email_origin');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_user'),
            ['user_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_email_origin'),
            ['origin_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
    }

    /**
     * Add oro_access_group foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroAccessGroupForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_access_group');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_business_unit'),
            ['business_unit_owner_id'],
            ['id'],
            ['onDelete' => 'SET NULL']
        );
    }

    /**
     * Add oro_user_access_group_role foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroUserAccessGroupRoleForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_user_access_group_role');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_access_group'),
            ['group_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_access_role'),
            ['role_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );
    }

    /**
     * Add oro_access_role foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroAccessRoleForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_access_role');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_business_unit'),
            ['business_unit_owner_id'],
            ['id'],
            ['onDelete' => 'SET NULL']
        );
    }

    /**
     * Add oro_user_status foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroUserStatusForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_user_status');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_user'),
            ['user_id'],
            ['id'],
            []
        );
    }
}
