-- Cache list of forms
CREATE TABLE IF NOT EXISTS plugin_helloasso_forms (
	id INTEGER PRIMARY KEY,
	org_name TEXT NOT NULL,
	name TEXT NOT NULL,
	type TEXT NOT NULL,
	status TEXT NOT NULL,

	org_slug TEXT NOT NULL,
	form_type TEXT NOT NULL,
	form_slug TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS plugin_helloasso_orders (
	id INTEGER PRIMARY KEY NOT NULL,
	id_user INTEGER NULL REFERENCES membres(id) ON DELETE SET NULL,
	date TEXT NOT NULL,
	amount INTEGER NOT NULL,
	status INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS plugin_helloasso_payments (
	id INTEGER PRIMARY KEY NOT NULL,
	id_order INTEGER NOT NULL REFERENCES plugin_helloasso_orders(id) ON DELETE CASCADE,
	id_user INTEGER NULL REFERENCES membres(id) ON DELETE SET NULL,
	amount INTEGER NOT NULL,
	state TEXT NOT NULL,
	date TEXT NOT NULL,
	receipt_url TEXT NULL
);

CREATE TABLE plugin_helloasso_targets_fields (
	id INTEGER PRIMARY KEY NOT NULL,
	id_target INTEGER NOT NULL REFERENCES plugin_helloasso_targets(id) ON DELETE CASCADE,
	source TEXT NOT NULL,
	target TEXT NULL
);

CREATE TABLE IF NOT EXISTS plugin_helloasso_targets (
-- List of forms that should create users or subscriptions
	id INTEGER PRIMARY KEY NOT NULL,

	label TEXT NOT NULL,

	org_slug TEXT NOT NULL,
	form_type TEXT NOT NULL,
	form_slug TEXT NOT NULL,

	last_sync TEXT NULL,

	-- If not null, create a user in this category
	id_category INTEGER NOT NULL REFERENCES users_categories(id) ON DELETE SET NULL,

	-- If not null, subscribe the user (if found) to this fee, and add payments to the subscription
	id_fee INTEGER NULL REFERENCES services_fees(id),

	-- If not null, creates transactions in this year and use this account as "bank"
	id_year INTEGER NULL REFERENCES acc_years(id),
	id_account_provider INTEGER NULL REFERENCES acc_accounts(id),
	id_account_donations INTEGER NULL REFERENCES acc_accounts(id),
	id_account_memberships INTEGER NULL REFERENCES acc_accounts(id),

	account_select_code TEXT NULL, -- Brindille code to select the target account, overrides id_account_donations and id_account_memberships

	CHECK (COALESCE(id_year, id_account_provider) IS NULL OR (id_account_provider IS NOT NULL AND id_year IS NOT NULL))
);

-- Make sure we can't link to an invalid account if the linked fee changes its accounting chart
CREATE TRIGGER IF NOT EXISTS plugin_helloasso_targets_fee_update AFTER UPDATE OF id_year ON services_fees BEGIN
    DELETE FROM plugin_helloasso_targets_payments WHERE id_account = NULL AND id_fee = OLD.id;
END;

CREATE TRIGGER IF NOT EXISTS plugin_helloasso_targets_fee_delete BEFORE DELETE ON services_fees BEGIN
    UPDATE plugin_helloasso_targets SET id_account = NULL, id_fee = NULL WHERE id_fee = OLD.id;
END;

CREATE TRIGGER IF NOT EXISTS plugin_helloasso_targets_fee_account_delete BEFORE DELETE ON acc_accounts BEGIN
    UPDATE plugin_helloasso_targets SET id_account = NULL WHERE id_fee_account = OLD.id;
END;

CREATE TRIGGER IF NOT EXISTS plugin_helloasso_targets_account_delete BEFORE DELETE ON acc_accounts BEGIN
    UPDATE plugin_helloasso_targets SET id_account1 = NULL, id_account2 = NULL, id_year = NULL WHERE id_account1 = OLD.id OR id_account2 = OLD.id;
END;

CREATE TRIGGER IF NOT EXISTS plugin_helloasso_targets_year_delete BEFORE DELETE ON acc_years BEGIN
    UPDATE plugin_helloasso_targets SET id_account1 = NULL, id_account2 = NULL, id_year = NULL WHERE id_year = OLD.id;
END;

CREATE TABLE IF NOT EXISTS plugin_helloasso_sync (
-- Contains the list of payments synced
	id INTEGER PRIMARY KEY,
	id_user INTEGER NULL REFERENCES membres (id),
	id_service_user INTEGER NULL REFERENCES services_users (id),

	order_id TEXT NOT NULL,
	payment_id TEXT NOT NULL,
	date TEXT NOT NULL,
	amount INTEGER NOT NULL,
	receipt_url TEXT
);

CREATE UNIQUE INDEX IF NOT EXISTS plugin_helloasso_sync_id ON plugin_helloasso_sync (payment_id);
CREATE INDEX IF NOT EXISTS plugin_helloasso_sync_date ON plugin_helloasso_sync (date);