<?php

#                if (version_compare(Webtrees::VERSION, '2.2.0', '>=')) {
#                    $this->setToParentPrivatePropertyWithReflection('list', DB::table('change')
#                        ->whereIn('change_id', function (Builder $query): void {
#                            $query->select([new Expression('MAX(change_id)')])
#                                ->from('change')
#                                ->where('gedcom_id', '=', $this->getFromParentPrivatePropertyWithReflection('tree')->id())
#                                ->where('status', '=', 'pending')
#                                ->groupBy(['xref']);
#                        })
#                        ->get()
#                        ->map(fn (object $row): GedcomRecord|null => Registry::gedcomRecordFactory()->make($row->xref, $this->getFromParentPrivatePropertyWithReflection('tree'), $row->new_gedcom ?: $row->old_gedcom))
#                        ->filter()
#                        ->all()
#                    );
#                } else {
                    $this->list = DB::table('change')
                        ->whereIn('change_id', function (Builder $query): void {
                            $query->select([new Expression('MAX(change_id)')])
                                ->from('change')
                                ->where('gedcom_id', '=', $this->tree->id())
                                ->where('status', '=', 'pending')
                                ->groupBy(['xref']);
                        })
                        ->get()
                        ->map(fn (object $row): ?GedcomRecord => Registry::gedcomRecordFactory()->make($row->xref, $this->tree, $row->new_gedcom ?: $row->old_gedcom))
                        ->filter()
                        ->all();
#                    }
