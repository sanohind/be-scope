
-- =====================================================================
-- BAGIAN 2: TABEL SINKRONISASI ERP (SQL Server / Database Mirror)
-- =====================================================================

CREATE TABLE stockbywh (
    partno VARCHAR(100) NOT NULL,
    warehouse VARCHAR(100) NOT NULL,
    desc VARCHAR(255) NULL,
    model VARCHAR(255) NULL,
    customer VARCHAR(255) NULL,
    onhand DECIMAL(10,2) NULL,
    safety_stock DECIMAL(10,2) NULL,
    group_type VARCHAR(100) NULL,
    PRIMARY KEY (partno, warehouse)
);

CREATE TABLE stock_by_wh_snapshots (
    snapshot_date DATE NOT NULL,
    partno VARCHAR(100) NOT NULL,
    warehouse VARCHAR(100) NOT NULL,
    onhand DECIMAL(10,2) NULL,
    created_at TIMESTAMP NULL,
    PRIMARY KEY (snapshot_date, partno, warehouse)
);

CREATE TABLE inventory_transaction (
    trans_id VARCHAR(100) NOT NULL,
    partno VARCHAR(100) NULL,
    warehouse VARCHAR(100) NULL,
    trans_date DATE NULL,
    trans_type VARCHAR(50) NULL,
    qty DECIMAL(10,2) NULL,
    receipt DECIMAL(10,2) NULL,
    shipment DECIMAL(10,2) NULL,
    PRIMARY KEY (trans_id)
);

CREATE TABLE view_warehouse_order (
    order_origin_code VARCHAR(100) NOT NULL,
    trx_type VARCHAR(50) NULL,
    order_date DATE NULL,
    plan_delivery_date DATE NULL,
    ship_from VARCHAR(100) NULL,
    ship_to VARCHAR(100) NULL,
    status_desc VARCHAR(100) NULL,
    PRIMARY KEY (order_origin_code)
);

CREATE TABLE view_prod_header (
    prod_no VARCHAR(100) NOT NULL,
    planning_date DATE NULL,
    item VARCHAR(100) NULL,
    customer VARCHAR(255) NULL,
    qty_order DECIMAL(10,2) NULL,
    qty_delivery DECIMAL(10,2) NULL,
    status VARCHAR(50) NULL,
    divisi VARCHAR(100) NULL,
    PRIMARY KEY (prod_no)
);

CREATE TABLE prod_report (
    prod_index BIGINT NOT NULL,
    production_order VARCHAR(100) NULL,
    part_number VARCHAR(100) NULL,
    transaction_date DATE NULL,
    qty_pelaporan DECIMAL(10,2) NULL,
    divisi VARCHAR(100) NULL,
    PRIMARY KEY (prod_index)
);

CREATE TABLE so_invoice_line (
    invoice_no VARCHAR(100) NOT NULL,
    sales_order VARCHAR(100) NULL,
    bp_name VARCHAR(255) NULL,
    invoice_date DATE NULL,
    part_no VARCHAR(100) NULL,
    delivered_qty DECIMAL(10,2) NULL,
    amount DECIMAL(15,2) NULL,
    PRIMARY KEY (invoice_no)
);

CREATE TABLE dn_detail (
    no_dn VARCHAR(100) NOT NULL,
    dn_supplier VARCHAR(255) NULL,
    plan_delivery_date DATE NULL,
    actual_receipt_date DATE NULL,
    part_no VARCHAR(100) NULL,
    dn_qty DECIMAL(10,2) NULL,
    receipt_qty DECIMAL(10,2) NULL,
    status_desc VARCHAR(100) NULL,
    PRIMARY KEY (no_dn)
);

CREATE TABLE employee_master (
    emp_id VARCHAR(50) NOT NULL,
    full_name VARCHAR(255) NULL,
    dept_name_en VARCHAR(255) NULL,
    pos_name_en VARCHAR(255) NULL,
    employment_status VARCHAR(100) NULL,
    PRIMARY KEY (emp_id)
);

CREATE TABLE attendance_by_period (
    attend_id BIGINT NOT NULL,
    emp_id VARCHAR(50) NULL,
    shiftstarttime TIMESTAMP NULL,
    shiftendtime TIMESTAMP NULL,
    total_ot DECIMAL(10,2) NULL,
    PRIMARY KEY (attend_id)
);

