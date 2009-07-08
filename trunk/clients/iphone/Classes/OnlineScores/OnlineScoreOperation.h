//
//  OnlineScoreOperation.h
//
//  Created by Andrew Kopanev on 4/8/09.
//  Copyright 2009 Injoit.com. All rights reserved.
//

#import <Foundation/Foundation.h>
#import "OAuthConsumer.h"
#import "XMLTreeParser.h"
#import "XMLTreeNode.h"

@interface OnlineScoreOperation : NSOperation {
	NSInteger  identifier;
	id         callbackTarget;
	SEL        callbackFinishSelector;
	SEL        callbackFailSelector;
	OAMutableURLRequest *request;
	id			pointer;
	XMLTreeParser *parser;
}

+ (id) queueDataRequest: (OAMutableURLRequest*) aRequest 
		 withIdentifier:(NSInteger)anIdentifier withCallbackTarget:(id)aTarget 
withCallbackFinishSelector:(SEL)aSelector withCallbackFailSelector: (SEL) aFailSelector;

@end
