<?php

namespace Oro\Bundle\EmailBundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;

use Oro\Bundle\MigrationBundle\Migration\Installation;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;
use Oro\Bundle\EmailBundle\Migrations\Schema\v1_0\OroEmailBundle;
use Oro\Bundle\EmailBundle\Migrations\Schema\v1_1\OroEmailBundle as OroEmailBundle11;
use Oro\Bundle\EmailBundle\Migrations\Schema\v1_3\OroEmailBundle as OroEmailBundle13;
use Oro\Bundle\EmailBundle\Migrations\Schema\v1_4\OroEmailBundle as OroEmailBundle14;
use Oro\Bundle\EmailBundle\Migrations\Schema\v1_5\OroEmailBundle as OroEmailBundle15;

class OroEmailBundleInstaller implements Installation
{
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
    public function up(Schema $schema, QueryBag $queries)
    {
        OroEmailBundle::oroEmailTable($schema, true, false);
        OroEmailBundle::oroEmailAddressTable($schema);
        OroEmailBundle::oroEmailAttachmentTable($schema);
        OroEmailBundle::oroEmailAttachmentContentTable($schema);
        OroEmailBundle::oroEmailBodyTable($schema);
        OroEmailBundle::oroEmailFolderTable($schema);
        OroEmailBundle::oroEmailOriginTable($schema);
        OroEmailBundle::oroEmailRecipientTable($schema);
        OroEmailBundle11::oroEmailToFolderRelationTable($schema);

        OroEmailBundle::oroEmailTemplateTable($schema);
        OroEmailBundle::oroEmailTemplateTranslationTable($schema);

        OroEmailBundle::oroEmailForeignKeys($schema, false);
        OroEmailBundle::oroEmailAttachmentForeignKeys($schema);
        OroEmailBundle::oroEmailAttachmentContentForeignKeys($schema);
        OroEmailBundle::oroEmailBodyForeignKeys($schema);
        OroEmailBundle::oroEmailFolderForeignKeys($schema);
        OroEmailBundle::oroEmailRecipientForeignKeys($schema);

        OroEmailBundle::oroEmailTemplateTranslationForeignKeys($schema);

        OroEmailBundle13::addOrganization($schema);

        OroEmailBundle14::up($schema, $queries);
        OroEmailBundle15::up($schema, $queries);
    }
}
