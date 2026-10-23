<ServerApplication xmlns="https://apps-api.moysklad.ru/xml/ns/appstore/app/v2"
                   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                   xsi:schemaLocation="https://apps-api.moysklad.ru/xml/ns/appstore/app/v2 https://apps-api.moysklad.ru/xml/ns/appstore/app/v2/application-v2.xsd">
    <iframes>
        <iframe type="main" sourceUrl="<?= cfg()->appBaseUrl ?>/entry/iframe.php" useContextKey="false">
            <uses>
                <user-context/>
            </uses>
        </iframe>
        <iframe type="chat" sourceUrl="<?= cfg()->appBaseUrl ?>/entry/iframe-chat.php" useContextKey="false">
            <uses>
                <user-context/>
            </uses>
        </iframe>
    </iframes>
    <vendorApi>
        <endpointBase><?= cfg()->appBaseUrl ?>/api/vendor-endpoint.php</endpointBase>
    </vendorApi>
    <!-- [feature:loyalty] пропуск на PUT .../loyalty в Vendor API, сам по себе ничего не включает -->
    <loyaltyApi/>
    <access>
        <resource>https://api.moysklad.ru/api/remap/1.2</resource>
        <scope>admin</scope>
    </access>
    <widgets>
        <document.customerorder.edit useContextKey="false">
            <sourceUrl><?= cfg()->appBaseUrl ?>/entry/widget-customerorder.php</sourceUrl>
            <height>
                <fixed>525px</fixed>
            </height>
            <supports>
                <open-feedback/>
                <dirty-state/>
                <save-handler/>
                <update-provider/>
                <change-handler>
                    <validation-feedback/>
                </change-handler>
            </supports>
            <uses>
                <good-folder-selector/>
                <standard-dialogs/>
                <navigation-service/>
                <user-context/>
            </uses>
        </document.customerorder.edit>
        <document.invoiceout.edit useContextKey="false">
            <sourceUrl><?= cfg()->appBaseUrl ?>/entry/widget-invoiceout.php</sourceUrl>
            <height>
                <fixed>525px</fixed>
            </height>
            <supports>
                <open-feedback/>
                <dirty-state/>
                <save-handler/>
                <update-provider/>
                <change-handler>
                    <validation-feedback/>
                </change-handler>
            </supports>
            <uses>
                <good-folder-selector/>
                <standard-dialogs/>
                <navigation-service/>
                <user-context/>
            </uses>
        </document.invoiceout.edit>
    </widgets>
    <popups>
        <popup>
            <name>some-popup</name>
            <sourceUrl><?= cfg()->appBaseUrl ?>/entry/popup.php</sourceUrl>
            <uses>
                <good-folder-selector/>
                <standard-dialogs/>
                <navigation-service/>
            </uses>
        </popup>
    </popups>
    <buttons>
        <button name="show-notification" title="Отобразить уведомление">
            <locations>
                <document.customerorder.edit/>
                <document.customerorder.list/>
            </locations>
        </button>
        <button name="navigate-to" title="Открыть ссылку">
            <locations>
                <document.customerorder.edit/>
            </locations>
        </button>
        <button name="show-popup" title="Открыть popup">
            <locations>
                <document.customerorder.edit/>
            </locations>
        </button>
    </buttons>
    <!-- [feature:scenario] действия решения в сценариях МоегоСклада, список общий с обработчиком -->
    <scenario>
<?php require_once __DIR__ . '/../scenario/scenario.inc.php'; ?>
<?php foreach (SCENARIO_ACTIONS as $name => $title): ?>
        <action name="<?= escHtml($name) ?>" title="<?= escHtml($title) ?>"/>
<?php endforeach; ?>
    </scenario>
</ServerApplication>
