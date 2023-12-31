-- Cache list of forms
CREATE TABLE IF NOT EXISTS plugin_helloasso_forms (
	id INTEGER PRIMARY KEY,

	org_name TEXT NOT NULL,
	org_slug TEXT NOT NULL,

	name TEXT NOT NULL,
	slug TEXT NOT NULL,
	type TEXT NOT NULL,
	state TEXT NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS plugin_helloasso_forms_key ON plugin_helloasso_forms(org_slug, slug);

CREATE TABLE IF NOT EXISTS plugin_helloasso_orders (
	id INTEGER PRIMARY KEY NOT NULL,
	id_form INTEGER NOT NULL REFERENCES plugin_helloasso_forms(id) ON DELETE CASCADE,
	id_user INTEGER NULL REFERENCES membres(id) ON DELETE SET NULL,
	id_transaction INTEGER NULL REFERENCES acc_transactions(id) ON DELETE SET NULL,
	date TEXT NOT NULL,
	person TEXT NOT NULL,
	amount INTEGER NOT NULL,
	status TEXT NOT NULL,
	raw_data TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS plugin_helloasso_items (
	id INTEGER PRIMARY KEY NOT NULL,
	id_form INTEGER NOT NULL REFERENCES plugin_helloasso_forms(id) ON DELETE CASCADE,
	id_order INTEGER NOT NULL REFERENCES plugin_helloasso_orders(id) ON DELETE CASCADE,
	id_user INTEGER NULL REFERENCES membres(id) ON DELETE SET NULL,
	type TEXT NOT NULL,
	state TEXT NOT NULL,
	person TEXT NOT NULL,
	label TEXT NOT NULL,
	amount INTEGER NOT NULL,
	raw_data TEXT NOT NULL,
	custom_fields TEXT NULL
);

/*
CREATE TABLE IF NOT EXISTS plugin_helloasso_options (
	id INTEGER PRIMARY KEY NOT NULL,
	id_order INTEGER NOT NULL REFERENCES plugin_helloasso_orders(id) ON DELETE CASCADE,
	hash TEXT NOT NULL,
	label TEXT NOT NULL,
	amount INTEGER NOT NULL,
	raw_data TEXT NOT NULL
);
*/

CREATE TABLE IF NOT EXISTS plugin_helloasso_payments (
	id INTEGER PRIMARY KEY NOT NULL,
	id_form INTEGER NOT NULL REFERENCES plugin_helloasso_forms(id) ON DELETE CASCADE,
	id_order INTEGER NOT NULL REFERENCES plugin_helloasso_orders(id) ON DELETE CASCADE,
	id_user INTEGER NULL REFERENCES membres(id) ON DELETE SET NULL,
	id_transaction INTEGER NULL REFERENCES acc_transactions(id) ON DELETE SET NULL,
	amount INTEGER NOT NULL,
	state TEXT NOT NULL,
	transfer_date TEXT NULL,
	person TEXT NULL,
	date TEXT NOT NULL,
	receipt_url TEXT NULL,
	raw_data TEXT NOT NULL
);


CREATE TABLE IF NOT EXISTS plugin_helloasso_targets (
-- List of forms that should create users or subscriptions
	id INTEGER PRIMARY KEY NOT NULL,
	id_form INTEGER NOT NULL REFERENCES plugin_helloasso_forms(id) ON DELETE CASCADE,

	label TEXT NOT NULL,
	last_sync TEXT NULL,

	-- If not null, create a user in this category
	id_category INTEGER NOT NULL REFERENCES users_categories(id) ON DELETE SET NULL,

	-- If not null, subscribe the user (if found) to this fee, and add payments to the subscription
	id_fee INTEGER NULL REFERENCES services_fees(id) ON DELETE SET NULL,

	-- If not null, creates transactions in this year
	id_year INTEGER NULL REFERENCES acc_years(id) ON DELETE SET NULL,

	split_payments INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS plugin_helloasso_targets_accounts (
	id INTEGER NOT NULL PRIMARY KEY,
	id_target INTEGER NOT NULL REFERENCES plugin_helloasso_targets (id) ON DELETE CASCADE,
	type TEXT NOT NULL,
	id_account INTEGER NOT NULL REFERENCES acc_accounts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS plugin_helloasso_targets_fields (
	id INTEGER PRIMARY KEY NOT NULL,
	id_target INTEGER NOT NULL REFERENCES plugin_helloasso_targets(id) ON DELETE CASCADE,
	source TEXT NOT NULL,
	target TEXT NULL
);

-- Make sure we can't link to an invalid account if the linked fee changes its accounting chart
CREATE TRIGGER IF NOT EXISTS plugin_helloasso_targets_fee_update AFTER UPDATE OF id_year ON services_fees BEGIN
    DELETE FROM plugin_helloasso_targets_payments WHERE id_account = NULL AND id_fee = OLD.id;
END;

CREATE TRIGGER IF NOT EXISTS plugin_helloasso_targets_fee_delete BEFORE DELETE ON services_fees BEGIN
    UPDATE plugin_helloasso_targets SET id_account = NULL, id_fee = NULL WHERE id_fee = OLD.id;
END;

CREATE TRIGGER IF NOT EXISTS plugin_helloasso_targets_year_delete BEFORE DELETE ON acc_years BEGIN
    DELETE FROM plugin_helloasso_targets_accounts WHERE id_target IN (SELECT id FROM plugin_helloasso_targets WHERE id_year = OLD.id);
END;
