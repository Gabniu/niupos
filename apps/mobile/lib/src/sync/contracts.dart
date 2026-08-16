import 'dart:convert';

const syncProtocolVersion = '1';

enum OutboxStatus { pending, sending, applied, rejected, conflict, retryPending }
enum SyncReceiptStatus { applied, rejected, conflict, retryPending }

final class SyncCommandReceipt {
  const SyncCommandReceipt({
    required this.commandId,
    required this.status,
    required this.attempts,
    this.resultCode,
    this.resultMessage,
  });

  final String commandId;
  final SyncReceiptStatus status;
  final int attempts;
  final String? resultCode;
  final String? resultMessage;
}

final class SyncPartition {
  const SyncPartition({required this.tenantId, required this.deviceId});

  final String tenantId;
  final String deviceId;

  String get key => '$tenantId:$deviceId';
}

final class OutboxCommand {
  OutboxCommand({
    required this.commandId,
    required this.type,
    required this.payload,
    required this.occurredAt,
    this.version = syncProtocolVersion,
    this.status = OutboxStatus.pending,
    this.attempts = 0,
    this.lastErrorCode,
  }) : fingerprintJson = _canonicalJson({
          'version': version,
          'commandId': commandId,
          'type': type,
          'occurredAt': occurredAt.toUtc().toIso8601String(),
          'payload': payload,
        });

  final String version;
  final String commandId;
  final String type;
  final Map<String, Object?> payload;
  final String fingerprintJson;
  final DateTime occurredAt;
  final OutboxStatus status;
  final int attempts;
  final String? lastErrorCode;

  OutboxCommand copyWith({
    OutboxStatus? status,
    int? attempts,
    String? lastErrorCode,
  }) =>
      OutboxCommand(
        version: version,
        commandId: commandId,
        type: type,
        payload: payload,
        occurredAt: occurredAt,
        status: status ?? this.status,
        attempts: attempts ?? this.attempts,
        lastErrorCode: lastErrorCode ?? this.lastErrorCode,
      );
}

final class ProjectionChange {
  const ProjectionChange({
    required this.cursor,
    required this.entityType,
    required this.entityId,
    required this.operation,
    required this.payload,
    required this.occurredAt,
    this.version = syncProtocolVersion,
  });

  final String version;
  final int cursor;
  final String entityType;
  final String entityId;
  final String operation;
  final Map<String, Object?> payload;
  final DateTime occurredAt;
}

final class SyncChangePage {
  const SyncChangePage({
    required this.cursor,
    required this.changes,
    required this.hasMore,
    this.version = syncProtocolVersion,
  });

  final String version;
  final int cursor;
  final List<ProjectionChange> changes;
  final bool hasMore;
}

String _canonicalJson(Object? value) {
  Object? sort(Object? current) {
    if (current is Map<String, Object?>) {
      final keys = current.keys.toList()..sort();
      return <String, Object?>{for (final key in keys) key: sort(current[key])};
    }
    if (current is List<Object?>) return current.map(sort).toList();
    return current;
  }

  return jsonEncode(sort(value));
}
