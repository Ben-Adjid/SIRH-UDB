DROP DATABASE IF EXISTS sirh_udb;
CREATE DATABASE sirh_udb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Copy all tables
CREATE TABLE sirh_udb.users LIKE sirh_test_temp.users;
INSERT INTO sirh_udb.users SELECT * FROM sirh_test_temp.users;

CREATE TABLE sirh_udb.migrations LIKE sirh_test_temp.migrations;
INSERT INTO sirh_udb.migrations SELECT * FROM sirh_test_temp.migrations;

CREATE TABLE sirh_udb.roles LIKE sirh_test_temp.roles;
INSERT INTO sirh_udb.roles SELECT * FROM sirh_test_temp.roles;

CREATE TABLE sirh_udb.permissions LIKE sirh_test_temp.permissions;
INSERT INTO sirh_udb.permissions SELECT * FROM sirh_test_temp.permissions;

CREATE TABLE sirh_udb.model_has_roles LIKE sirh_test_temp.model_has_roles;
INSERT INTO sirh_udb.model_has_roles SELECT * FROM sirh_test_temp.model_has_roles;

CREATE TABLE sirh_udb.model_has_permissions LIKE sirh_test_temp.model_has_permissions;
INSERT INTO sirh_udb.model_has_permissions SELECT * FROM sirh_test_temp.model_has_permissions;

CREATE TABLE sirh_udb.role_has_permissions LIKE sirh_test_temp.role_has_permissions;
INSERT INTO sirh_udb.role_has_permissions SELECT * FROM sirh_test_temp.role_has_permissions;

CREATE TABLE sirh_udb.ufrs LIKE sirh_test_temp.ufrs;
INSERT INTO sirh_udb.ufrs SELECT * FROM sirh_test_temp.ufrs;

CREATE TABLE sirh_udb.teachers LIKE sirh_test_temp.teachers;
INSERT INTO sirh_udb.teachers SELECT * FROM sirh_test_temp.teachers;

-- Copy remaining tables
CREATE TABLE sirh_udb.personal_access_tokens LIKE sirh_test_temp.personal_access_tokens;
INSERT INTO sirh_udb.personal_access_tokens SELECT * FROM sirh_test_temp.personal_access_tokens;

CREATE TABLE sirh_udb.password_reset_tokens LIKE sirh_test_temp.password_reset_tokens;
INSERT INTO sirh_udb.password_reset_tokens SELECT * FROM sirh_test_temp.password_reset_tokens;

CREATE TABLE sirh_udb.sessions LIKE sirh_test_temp.sessions;
INSERT INTO sirh_udb.sessions SELECT * FROM sirh_test_temp.sessions;

CREATE TABLE sirh_udb.departments LIKE sirh_test_temp.departments;
INSERT INTO sirh_udb.departments SELECT * FROM sirh_test_temp.departments;

CREATE TABLE sirh_udb.subjects LIKE sirh_test_temp.subjects;
INSERT INTO sirh_udb.subjects SELECT * FROM sirh_test_temp.subjects;

CREATE TABLE sirh_udb.classrooms LIKE sirh_test_temp.classrooms;
INSERT INTO sirh_udb.classrooms SELECT * FROM sirh_test_temp.classrooms;

CREATE TABLE sirh_udb.schedules LIKE sirh_test_temp.schedules;
INSERT INTO sirh_udb.schedules SELECT * FROM sirh_test_temp.schedules;

CREATE TABLE sirh_udb.homeworks LIKE sirh_test_temp.homeworks;
INSERT INTO sirh_udb.homeworks SELECT * FROM sirh_test_temp.homeworks;

CREATE TABLE sirh_udb.grades LIKE sirh_test_temp.grades;
INSERT INTO sirh_udb.grades SELECT * FROM sirh_test_temp.grades;

CREATE TABLE sirh_udb.attendance_codes LIKE sirh_test_temp.attendance_codes;
INSERT INTO sirh_udb.attendance_codes SELECT * FROM sirh_test_temp.attendance_codes;

CREATE TABLE sirh_udb.attendances LIKE sirh_test_temp.attendances;
INSERT INTO sirh_udb.attendances SELECT * FROM sirh_test_temp.attendances;

CREATE TABLE sirh_udb.leave_types LIKE sirh_test_temp.leave_types;
INSERT INTO sirh_udb.leave_types SELECT * FROM sirh_test_temp.leave_types;

CREATE TABLE sirh_udb.leave_balances LIKE sirh_test_temp.leave_balances;
INSERT INTO sirh_udb.leave_balances SELECT * FROM sirh_test_temp.leave_balances;

CREATE TABLE sirh_udb.leaves LIKE sirh_test_temp.leaves;
INSERT INTO sirh_udb.leaves SELECT * FROM sirh_test_temp.leaves;

CREATE TABLE sirh_udb.payrolls LIKE sirh_test_temp.payrolls;
INSERT INTO sirh_udb.payrolls SELECT * FROM sirh_test_temp.payrolls;

CREATE TABLE sirh_udb.payroll_details LIKE sirh_test_temp.payroll_details;
INSERT INTO sirh_udb.payroll_details SELECT * FROM sirh_test_temp.payroll_details;

CREATE TABLE sirh_udb.notifications LIKE sirh_test_temp.notifications;
INSERT INTO sirh_udb.notifications SELECT * FROM sirh_test_temp.notifications;

CREATE TABLE sirh_udb.documents LIKE sirh_test_temp.documents;
INSERT INTO sirh_udb.documents SELECT * FROM sirh_test_temp.documents;

CREATE TABLE sirh_udb.audit_logs LIKE sirh_test_temp.audit_logs;
INSERT INTO sirh_udb.audit_logs SELECT * FROM sirh_test_temp.audit_logs;

CREATE TABLE sirh_udb.holidays LIKE sirh_test_temp.holidays;
INSERT INTO sirh_udb.holidays SELECT * FROM sirh_test_temp.holidays;

CREATE TABLE sirh_udb.email_verification_tokens LIKE sirh_test_temp.email_verification_tokens;
INSERT INTO sirh_udb.email_verification_tokens SELECT * FROM sirh_test_temp.email_verification_tokens;

CREATE TABLE sirh_udb.cache LIKE sirh_test_temp.cache;
INSERT INTO sirh_udb.cache SELECT * FROM sirh_test_temp.cache;

CREATE TABLE sirh_udb.cache_locks LIKE sirh_test_temp.cache_locks;
INSERT INTO sirh_udb.cache_locks SELECT * FROM sirh_test_temp.cache_locks;

CREATE TABLE sirh_udb.failed_jobs LIKE sirh_test_temp.failed_jobs;
INSERT INTO sirh_udb.failed_jobs SELECT * FROM sirh_test_temp.failed_jobs;

CREATE TABLE sirh_udb.jobs LIKE sirh_test_temp.jobs;
INSERT INTO sirh_udb.jobs SELECT * FROM sirh_test_temp.jobs;

CREATE TABLE sirh_udb.job_batches LIKE sirh_test_temp.job_batches;
INSERT INTO sirh_udb.job_batches SELECT * FROM sirh_test_temp.job_batches;

CREATE TABLE sirh_udb.teaching_types LIKE sirh_test_temp.teaching_types;
INSERT INTO sirh_udb.teaching_types SELECT * FROM sirh_test_temp.teaching_types;

CREATE TABLE sirh_udb.teacher_pin_displays LIKE sirh_test_temp.teacher_pin_displays;
INSERT INTO sirh_udb.teacher_pin_displays SELECT * FROM sirh_test_temp.teacher_pin_displays;

DROP DATABASE sirh_test_temp;
