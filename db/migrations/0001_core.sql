create table account_application (
    account_id text not null,
    application_id text not null,
    status integer,
    access_token text,
    info_message text,
    store text,
    created_at text not null,
    updated_at text not null,
    primary key (account_id, application_id)
);

create table jwt (
    jti text primary key,
    expires_at bigint not null
);
