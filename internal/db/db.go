package db

import (
	"context"
	"fmt"
	"log/slog"
	"time"

	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgxpool"
)

type Config struct {
	Host     string
	Port     string
	Database string
	User     string
	Password string
}

func NewPool(ctx context.Context, cfg Config, logger *slog.Logger) (*pgxpool.Pool, error) {
	dsn := fmt.Sprintf(
		"host=%s port=%s dbname=%s user=%s password=%s",
		cfg.Host, cfg.Port, cfg.Database, cfg.User, cfg.Password,
	)
	poolCfg, err := pgxpool.ParseConfig(dsn)
	if err != nil {
		return nil, fmt.Errorf("parse db config: %w", err)
	}
	if logger != nil {
		poolCfg.ConnConfig.Tracer = &queryTracer{logger: logger}
	}
	pool, err := pgxpool.NewWithConfig(ctx, poolCfg)
	if err != nil {
		return nil, fmt.Errorf("create pool: %w", err)
	}
	if err := pool.Ping(ctx); err != nil {
		pool.Close()
		return nil, fmt.Errorf("ping database: %w", err)
	}
	return pool, nil
}

type traceKey struct{}

type traceData struct {
	start time.Time
	sql   string
}

type queryTracer struct{ logger *slog.Logger }

func (t *queryTracer) TraceQueryStart(ctx context.Context, _ *pgx.Conn, data pgx.TraceQueryStartData) context.Context {
	sql := data.SQL
	if len(sql) > 120 {
		sql = sql[:120] + "…"
	}
	return context.WithValue(ctx, traceKey{}, traceData{start: time.Now(), sql: sql})
}

func (t *queryTracer) TraceQueryEnd(ctx context.Context, _ *pgx.Conn, data pgx.TraceQueryEndData) {
	td, _ := ctx.Value(traceKey{}).(traceData)
	dur := time.Since(td.start)
	if data.Err != nil {
		t.logger.Error("query", "sql", td.sql, "duration_ms", dur.Milliseconds(), "err", data.Err)
	} else {
		t.logger.Debug("query", "sql", td.sql, "duration_ms", dur.Milliseconds())
	}
}
