# NOVA Mobile

Flutter-ready offline synchronization core for the owner/manager client. The
current slice deliberately has no UI or network adapter: it establishes the
tenant/device-partitioned local repository and versioned protocol models that a
Flutter shell can consume after the server contract is frozen. Use
`DurableSyncRepository` with a `SyncSecureStorage` implementation backed by
Keychain, Keystore, or an equivalent OS secure store; the Dart core serializes
state but does not encrypt it itself. Corrupt partitions fail closed and must be
explicitly reset before bootstrapping again.

Run with a Dart 3.6+ SDK:

```sh
dart pub get
dart analyze
dart test
```

The repository does not currently bundle a Dart or Flutter SDK.
