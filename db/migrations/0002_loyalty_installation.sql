-- [feature:loyalty] хранилище подключения программы лояльности, модуль app/loyalty
create table loyalty_installation (
    application_id text not null,
    account_id text not null,
    provider_token text,
    external_search integer not null default 0,
    connected_at text,
    pending_provider_token text,
    pending_external_search integer not null default 0,
    created_at text not null,
    updated_at text not null,
    primary key (application_id, account_id)
);
