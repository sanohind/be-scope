-- =====================================================================
-- DDL SCRIPT UNTUK ORACLE DATA MODELER (SISTEM SCOPE)
-- =====================================================================
-- Script ini berisi rancangan fisik tabel-tabel Sistem SCOPE yang 
-- mencakup Basis Data Lokal (MySQL) dan Tabel Referensi ERP.
-- Anda dapat meng-import script ini ke Oracle SQL Developer Data Modeler
-- melalui menu: File > Import > DDL File.
-- =====================================================================

-- =====================================================================
-- BAGIAN 1: BASIS DATA LOKAL SCOPE (MySQL)
-- =====================================================================

CREATE TABLE users (
    id BIGINT NOT NULL,
    username VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id)
);

CREATE TABLE sync_logs (
    id BIGINT NOT NULL,
    sync_type VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    total_records INT NULL,
    success_records INT NULL,
    failed_records INT NULL,
    error_message TEXT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE asakai_titles (
    id BIGINT NOT NULL,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id)
);

CREATE TABLE asakai_targets (
    id BIGINT NOT NULL,
    asakai_title_id BIGINT NOT NULL,
    year INT NOT NULL,
    period INT NOT NULL,
    target DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (asakai_title_id) REFERENCES asakai_titles(id)
);

CREATE TABLE asakai_charts (
    id BIGINT NOT NULL,
    asakai_title_id BIGINT NOT NULL,
    date DATE NOT NULL,
    qty DECIMAL(10,2) NOT NULL,
    user_id BIGINT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (asakai_title_id) REFERENCES asakai_titles(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE asakai_reasons (
    id BIGINT NOT NULL,
    asakai_chart_id BIGINT NOT NULL,
    date DATE NOT NULL,
    part_no VARCHAR(100) NULL,
    part_name VARCHAR(255) NULL,
    problem TEXT NOT NULL,
    qty DECIMAL(10,2) NULL,
    section VARCHAR(100) NULL,
    line VARCHAR(100) NULL,
    penyebab TEXT NOT NULL,
    perbaikan TEXT NOT NULL,
    user_id BIGINT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (asakai_chart_id) REFERENCES asakai_charts(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE daily_use_wh (
    id BIGINT NOT NULL,
    partno VARCHAR(100) NOT NULL,
    warehouse VARCHAR(100) NOT NULL,
    year INT NOT NULL,
    period INT NOT NULL,
    qty INT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id)
);

CREATE TABLE production_plan (
    id BIGINT NOT NULL,
    partno VARCHAR(100) NOT NULL,
    divisi VARCHAR(100) NOT NULL,
    qty_plan DECIMAL(10,2) NOT NULL,
    plan_date DATE NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id)
);

CREATE TABLE wh_delivery_plan (
    id BIGINT NOT NULL,
    partno VARCHAR(100) NOT NULL,
    warehouse VARCHAR(100) NOT NULL,
    qty_delivery DECIMAL(10,2) NOT NULL,
    delivery_date DATE NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id)
);
