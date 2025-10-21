# OWASP-based security checklist

This checklist adapts the [OWASP Application Security Verification Standard (ASVS)](https://owasp.org/ASVS/) to the Caope deployment. Use it during development, pre-release reviews, and recurring operational audits. Document the date, reviewer, and evidence for every item.

## 1. Architecture, design, and threat modelling
- [ ] Identify critical assets (PII, payment data, service credentials) and keep a data flow diagram alongside [`docs/deployment.md`](deployment.md).
- [ ] Maintain an up-to-date threat model that covers authentication, session management, business logic, and data storage.
- [ ] Ensure third-party services (payment gateways, email providers) have been vetted for compliance requirements.

## 2. Authentication and session management
- [ ] Enforce multi-factor authentication for privileged accounts and document exceptions in [`docs/two-factor-authentication.md`](two-factor-authentication.md).
- [ ] Store passwords using Argon2id (or stronger) with per-user salts and a cost review every six months.
- [ ] Configure session cookies as `HttpOnly`, `Secure`, with `SameSite=Lax` or stricter; invalidate sessions on logout and credential changes.

## 3. Access control
- [ ] Apply least privilege to application roles; review role matrices quarterly.
- [ ] Guard every controller action with explicit authorization logic and deny-by-default policies.
- [ ] Log and alert on repeated authorization failures from the same user or source IP.

## 4. Input validation, encoding, and output handling
- [ ] Centralize validation in request objects or form requests; reject or sanitize untrusted input.
- [ ] Encode output using the correct context (HTML, JavaScript, SQL) to prevent injection vulnerabilities.
- [ ] Maintain dependency pinning and run automated SCA (Software Composition Analysis) on every merge into `main`.

## 5. Cryptography and secrets management
- [ ] Rotate application secrets (API keys, encryption keys) at least annually and whenever personnel changes.
- [ ] Store secrets exclusively in the runtime environment or secret manager; never commit them to Git.
- [ ] Verify HTTPS/TLS configuration quarterly, ensuring modern ciphers and automatic certificate renewal.

## 6. Data protection and privacy
- [ ] Inventory stored personal data and document retention policies.
- [ ] Encrypt data at rest when supported by the storage technology (MySQL TDE, encrypted volumes, etc.).
- [ ] Publish and maintain breach notification procedures aligned with local regulations.

## 7. Error handling, logging, and monitoring
- [ ] Use structured logging and redact sensitive fields before logs leave the application boundary.
- [ ] Configure alerting for critical events (login failures, privilege escalations, backups failing).
- [ ] Review log retention and access controls; audit who can read security logs.

## 8. Data backup and recovery
- [ ] Confirm automated backups for SQLite and MySQL are scheduled and monitored; align with [`docs/restore-runbook.md`](restore-runbook.md).
- [ ] Perform restore drills at least twice per year and capture the results in the runbook.
- [ ] Keep offline or offsite copies for disaster recovery scenarios.

## 9. Secure deployment and operations
- [ ] Follow the operational security guidance documented in [`docs/deployment.md`](deployment.md#operational-security).
- [ ] Review GitHub Actions secrets quarterly; remove obsolete secrets and rotate active ones.
- [ ] Run infrastructure vulnerability scans (server OS, containers) with documented remediation timelines.

## 10. Continuous improvement
- [ ] Track remediation tasks in the issue tracker with owners and due dates.
- [ ] Update this checklist whenever new OWASP ASVS requirements become relevant to the project.
- [ ] Present security posture metrics (patch latency, MFA coverage) during quarterly reviews with leadership.
