# NOVA Mobile

Flutter-ready offline synchronization core for the owner/manager client. The
current slice deliberately has no UI or network adapter: it establishes the
tenant/device-partitioned local repository and versioned protocol models that a
Flutter shell can consume after the server contract is frozen.

Run with a Dart 3.6+ SDK:

```sh
dart pub get
dart analyze
dart test
```

The repository does not currently bundle a Dart or Flutter SDK.
