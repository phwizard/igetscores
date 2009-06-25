//
//  XMLTreeParser.h
//  daypack
//
//  Created by John Stump on 2/15/09.
//  Copyright 2009 John Stump. All rights reserved.
//

#import <Foundation/Foundation.h>

@class XMLTreeNode;

@interface XMLTreeParser : NSObject {
	XMLTreeNode* root;
	NSMutableArray* stack;
}

@property (nonatomic,retain) XMLTreeNode* root;
@property (nonatomic,retain) NSMutableArray* stack;

- (XMLTreeParser*) init;
- (void) dealloc;

// start parsing, return root
- (XMLTreeNode*) parse:(NSData*) data;

@end
